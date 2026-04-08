/**
 * notifications.js — Centro de notificações + aprovação de pedidos E2EE
 */

const NotificationsManager = {

    privateKey: null,

    async init() {
        this.privateKey = await VaultCrypto.loadPrivateKeyFromSession();
        if (!this.privateKey) {
            Toast.error('Sessão expirou. Por favor faz login novamente.');
            setTimeout(() => location.href = '../login.php', 2000);
            return;
        }
        await this.load();
    },

    async load() {
        const wrap = document.getElementById('notif-wrap');
        if (!wrap) return;
        wrap.innerHTML = '<div class="loading-overlay"><span class="spinner"></span></div>';

        const r = await API.get('api/notifications.php?action=list');
        if (!r.success) { wrap.innerHTML = `<div class="alert alert-error">${r.error}</div>`; return; }

        // Mark all read
        API.post('api/notifications.php?action=mark_read', {});

        const { notifications } = r.data;
        if (!notifications.length) {
            wrap.innerHTML = `
              <div class="empty-state">
                <div class="empty-icon">🔔</div>
                <div class="empty-title">Sem notificações</div>
                <div class="empty-text">Quando alguém te enviar um pedido, aparecerá aqui.</div>
              </div>`;
            return;
        }

        wrap.innerHTML = `<div class="notif-list">${notifications.map(n => this.renderNotif(n)).join('')}</div>`;
        this.bindEvents();
    },

    renderNotif(n) {
        const unread  = !n.read_at ? 'unread' : '';
        const icons   = { view_request:'👁', add_request:'➕', access_granted:'✅', access_denied:'❌' };
        const iconBg  = { view_request:'notif-icon-blue', add_request:'notif-icon-blue', access_granted:'notif-icon-green', access_denied:'notif-icon-red' };
        const icon    = icons[n.type] || '🔔';
        const bg      = iconBg[n.type] || 'notif-icon-blue';
        const isPending = n.type === 'view_request' || n.type === 'add_request';
        const actions = isPending && n.related_id ? `
          <div class="notif-actions">
            <button class="btn btn-success btn-sm btn-approve" data-req="${n.related_id}" data-type="${n.type}">✅ Aprovar</button>
            <button class="btn btn-danger btn-sm btn-deny" data-req="${n.related_id}">❌ Negar</button>
          </div>` : '';

        return `
          <div class="notif-item ${unread}" id="notif-${n.id}">
            <div class="notif-icon ${bg}">${icon}</div>
            <div class="notif-content">
              <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div class="notif-title">${escHtml(n.title)}</div>
                <button class="btn btn-icon btn-sm btn-delete-notif" data-id="${n.id}" title="Apagar" style="border:none;background:transparent;width:auto;height:auto;padding:2px">🗑</button>
              </div>
              <div class="notif-body">${escHtml(n.body || '')}</div>
              ${actions}
              <div class="notif-time">${timeAgoJs(n.created_at)}</div>
            </div>
            ${unread ? '<div class="unread-dot"></div>' : ''}
          </div>`;
    },

    async deleteNotification(notifId) {
        if (!confirm('Apagar esta notificação?')) return;
        const r = await API.post('api/notifications.php?action=delete', { notification_id: notifId });
        if (r.success) {
            document.getElementById(`notif-${notifId}`)?.remove();
            Toast.success('Notificação apagada.');
        } else Toast.error(r.error);
    },

    async approveRequest(reqId, type) {
        const btn = document.querySelector(`.btn-approve[data-req="${reqId}"]`);
        setLoading(btn, true, 'A processar...');

        let reEncryptedAesKey = null;

        if (type === 'view_request') {
            // Precisamos re-encriptar a AES key para o solicitante
            const pending = await API.get('api/access_requests.php?action=pending_for_me');
            if (!pending.success) { Toast.error(pending.error); setLoading(btn, false); return; }

            const req = pending.data.requests.find(r => r.id === reqId);
            if (!req) { Toast.error('Pedido não encontrado.'); setLoading(btn, false); return; }

            if (!req.my_encrypted_aes_key) {
                Toast.error('Não tens a AES key desta credencial. Não podes aprovar este pedido (a credencial pode ter sido adicionada por outro técnico).');
                setLoading(btn, false); return;
            }

            try {
                // Buscar chave pública do solicitante
                const pkRes = await API.get(`api/public_key.php?user_id=${req.requester_id}`);
                if (!pkRes.success) { Toast.error('Erro ao obter chave pública do solicitante.'); setLoading(btn, false); return; }
                const requesterPubKey = await VaultCrypto.importPublicKey(pkRes.data.public_key);

                // Re-encriptar: decrypt com a minha privKey → encrypt com a pubKey do solicitante
                reEncryptedAesKey = await VaultCrypto.reEncryptCredentialKey(
                    req.my_encrypted_aes_key,
                    this.privateKey,
                    requesterPubKey
                );
            } catch (e) {
                Toast.error('Erro na re-encriptação da chave. ' + e.message);
                setLoading(btn, false); return;
            }
        }

        const r = await API.post('api/access_requests.php?action=approve', {
            request_id: reqId,
            re_encrypted_aes_key: reEncryptedAesKey
        });

        if (r.success) {
            Toast.success('Pedido aprovado!');
            btn.closest('.notif-item')?.remove();
            await this.load();
        } else {
            Toast.error(r.error);
            setLoading(btn, false);
        }
    },

    async denyRequest(reqId) {
        if (!confirm('Tens a certeza que queres negar este pedido?')) return;
        const btn = document.querySelector(`.btn-deny[data-req="${reqId}"]`);
        const r = await API.post('api/access_requests.php?action=deny', { request_id: reqId });
        if (r.success) { 
            Toast.success('Pedido negado.'); 
            btn?.closest('.notif-item')?.remove();
            await this.load(); 
        }
        else Toast.error(r.error);
    },

    bindEvents() {
        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', () => this.approveRequest(parseInt(btn.dataset.req), btn.dataset.type));
        });
        document.querySelectorAll('.btn-deny').forEach(btn => {
            btn.addEventListener('click', () => this.denyRequest(parseInt(btn.dataset.req)));
        });
        document.querySelectorAll('.btn-delete-notif').forEach(btn => {
            btn.addEventListener('click', () => this.deleteNotification(parseInt(btn.dataset.id)));
        });
    }
};

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function timeAgoJs(dt) {
    const diff = (Date.now() - new Date(dt).getTime()) / 1000;
    if (diff < 60)     return 'agora';
    if (diff < 3600)   return Math.floor(diff/60) + ' min atrás';
    if (diff < 86400)  return Math.floor(diff/3600) + 'h atrás';
    return Math.floor(diff/86400) + 'd atrás';
}
