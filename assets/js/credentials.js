/**
 * credentials.js — Gestão de credenciais E2EE
 */

const CredentialsManager = {

    privateKey: null,
    companyId:  null,
    isOwner:    false,
    ownerId:    null,

    async init(companyId, isOwner, ownerId) {
        this.companyId = companyId;
        this.isOwner   = isOwner;
        this.ownerId   = ownerId;
        this.privateKey = await VaultCrypto.loadPrivateKeyFromSession();
        if (!this.privateKey) {
            Toast.error('Sessão expirou. Por favor faz login novamente.');
            setTimeout(() => location.href = 'login.php', 2000);
            return;
        }
        await this.loadCredentials();
        this.bindEvents();
    },

    async loadCredentials() {
        const list = document.getElementById('credentials-list');
        if (!list) return;
        list.innerHTML = '<div class="loading-overlay"><span class="spinner"></span> A carregar...</div>';

        const r = await API.get(`api/credentials.php?action=list&company_id=${this.companyId}`);
        if (!r.success) { list.innerHTML = `<div class="alert alert-error">${r.error}</div>`; return; }

        const { credentials } = r.data;
        if (!credentials.length) {
            list.innerHTML = `
              <div class="empty-state">
                <div class="empty-icon">🔑</div>
                <div class="empty-title">Sem credenciais</div>
                <div class="empty-text">Ainda não há credenciais nesta empresa.</div>
              </div>`;
            return;
        }

        list.innerHTML = credentials.map(cr => this.renderCredentialItem(cr)).join('');
    },

    renderCredentialItem(cr) {
        const canView  = cr.has_access;
        const isPrivate = parseInt(cr.is_private);
        return `
          <div class="credential-item" data-cred-id="${cr.id}">
            <div class="cred-info">
              <div class="cred-icon">🔑</div>
              <div>
                <div class="cred-label">${escHtml(cr.label)}</div>
                <div class="cred-added">Adicionado por ${escHtml(cr.added_by_username)} · ${timeAgoJs(cr.created_at)}</div>
              </div>
            </div>
            <div class="cred-actions">
              ${isPrivate ? '<span class="cred-private-badge">🔒 Privado</span>' : ''}
              ${canView
                ? `<button class="btn btn-icon btn-view-cred" data-id="${cr.id}" title="Ver credencial">👁</button>`
                : `<button class="btn btn-icon btn-request-view" data-id="${cr.id}" title="Pedir acesso">🔐</button>`
              }
              ${parseInt(cr.is_mine) ? `<button class="btn btn-icon btn-delete-cred" data-id="${cr.id}" title="Eliminar" style="color:var(--red)">🗑</button>` : ''}
            </div>
          </div>`;
    },

    async viewCredential(credId) {
        const r = await API.get(`api/credentials.php?action=get_encrypted&credential_id=${credId}`);
        if (!r.success) { Toast.error(r.error); return; }
        const { encrypted_data, iv, encrypted_aes_key } = r.data;

        try {
            const aesKey = await VaultCrypto.decryptCredentialKey(encrypted_aes_key, this.privateKey);
            const data   = await VaultCrypto.decryptCredential(encrypted_data, iv, aesKey);
            this.showRevealModal(data);
        } catch (e) {
            Toast.error('Erro ao desencriptar. Chave inválida ou corrompida.');
        }
    },

    showRevealModal(data) {
        const modal = document.getElementById('reveal-modal');
        if (!modal) return;
        document.getElementById('reveal-username').textContent = data.username || '—';
        document.getElementById('reveal-password').textContent = data.password || '—';
        document.getElementById('reveal-url').textContent = data.url || '—';
        document.getElementById('reveal-notes').textContent = data.notes || '—';
        openModal('reveal-modal');
    },

    async requestViewAccess(credId) {
        const r = await API.post('api/access_requests.php?action=request_view', { credential_id: credId });
        if (r.success) Toast.success('Pedido enviado! Aguarda aprovação do dono.');
        else Toast.error(r.error);
    },

    async addCredential(formData) {
        const { label, username, password, url, notes } = formData;
        if (!label || !username || !password) { Toast.error('Label, username e password são obrigatórios.'); return; }

        // Gerar AES key aleatória
        const aesKey    = await VaultCrypto.generateCredentialKey();
        // Encriptar dados
        const { ciphertext, iv } = await VaultCrypto.encryptCredential({ username, password, url, notes }, aesKey);

        // Buscar a nossa própria chave pública para encriptar a AES key
        const user = VaultCrypto.getUserFromSession();
        if (!user?.public_key) { Toast.error('Sessão inválida.'); return; }
        const pubKey = await VaultCrypto.importPublicKey(user.public_key);
        const encAesKey = await VaultCrypto.encryptCredentialKey(aesKey, pubKey);

        const r = await API.post('api/credentials.php?action=add', {
            company_id:       this.companyId,
            label,
            encrypted_data:   ciphertext,
            iv,
            encrypted_aes_key: encAesKey
        });
        if (r.success) {
            Toast.success('Credencial adicionada com sucesso!');
            closeModal('add-cred-modal');
            await this.loadCredentials();
        } else {
            Toast.error(r.error);
        }
    },

    async deleteCredential(credId) {
        if (!confirm('Tens a certeza que queres eliminar esta credencial?')) return;
        const r = await API.post('api/credentials.php?action=delete', { credential_id: credId });
        if (r.success) { Toast.success('Credencial eliminada.'); await this.loadCredentials(); }
        else Toast.error(r.error);
    },
    async inviteTechnician(email) {
        if (!email) return;
        // 1. Obter a chave pública do destinatário pelo e-mail
        const pkRes = await API.get(`api/public_key.php?email=${encodeURIComponent(email)}`);
        if (!pkRes.success || !pkRes.data) {
            Toast.error(pkRes.error || 'Conta VaultKeeper não encontrada com esse e-mail.');
            return;
        }
        const techPubKeyRaw = pkRes.data.public_key;
        if (!techPubKeyRaw) { Toast.error('O utilizador não possui Chave Pública configurada.'); return; }
        
        let techPubKey;
        try {
            techPubKey = await VaultCrypto.importPublicKey(techPubKeyRaw);
        } catch(e) {
            Toast.error('Erro ao processar chave pública do técnico.');
            return;
        }

        // 2. Extrair a matriz de credenciais da nossa empresa às quais temos acesso completo
        const myKeysRes = await API.get(`api/credentials.php?action=get_my_company_keys&company_id=${this.companyId}`);
        if (!myKeysRes.success) { Toast.error('Erro ao listar Base Criptográfica da empresa.'); return; }
        
        const myKeys = myKeysRes.data.keys || [];
        const megaPayload = [];

        // 3. Fase Crítica: Mega-Payload E2EE (Desencripta localmente -> Re-encritpa p/ o técnico)
        for (const k of myKeys) {
            try {
                const aesKey = await VaultCrypto.decryptCredentialKey(k.encrypted_aes_key, this.privateKey);
                const techEncAes = await VaultCrypto.encryptCredentialKey(aesKey, techPubKey);
                megaPayload.push({ credential_id: k.credential_id, encrypted_aes_key: techEncAes });
            } catch(e) {
                console.warn('Alerta Zero-Knowledge: Chave ID ' + k.credential_id + ' ignorada por falta de autorização original.');
            }
        }

        // 4. Transportar mega pacote
        const r = await API.post('api/access_requests.php?action=invite_technician', {
            company_id: this.companyId,
            technician_email: email,
            encrypted_keys: megaPayload
        });

        if (r.success) {
            Toast.success('Convite Criptografado enviado com sucesso!');
        } else {
            Toast.error(r.error || 'Falha ao enviar convite E2EE.');
        }
    },

    bindEvents() {
        const list = document.getElementById('credentials-list');
        if (list) {
            list.addEventListener('click', async e => {
                const viewBtn   = e.target.closest('.btn-view-cred');
                const reqBtn    = e.target.closest('.btn-request-view');
                const delBtn    = e.target.closest('.btn-delete-cred');
                if (viewBtn)  await this.viewCredential(viewBtn.dataset.id);
                if (reqBtn)   await this.requestViewAccess(reqBtn.dataset.id);
                if (delBtn)   await this.deleteCredential(delBtn.dataset.id);
            });
        }

        document.addEventListener('modalClosed', async e => {
            if (e.detail === 'reveal-modal') {
                // Ao fechar, limpa da memória da UI
                document.getElementById('reveal-username').textContent = '—';
                document.getElementById('reveal-password').textContent = '—';
                document.getElementById('reveal-url').textContent = '—';
                document.getElementById('reveal-notes').textContent = '—';
                // Recarrega as credenciais (vai trancar o cadeado daquela credencial que foi queimada)
                await this.loadCredentials();
            }
        });

        document.getElementById('add-cred-form')?.addEventListener('submit', async e => {
            e.preventDefault();
            const f   = e.target;
            const btn = f.querySelector('[type=submit]');
            setLoading(btn, true, 'A encriptar...');
            await this.addCredential({
                label:    f.querySelector('[name=label]').value,
                username: f.querySelector('[name=username]').value,
                password: f.querySelector('[name=password]').value,
                url:      f.querySelector('[name=url]')?.value || '',
                notes:    f.querySelector('[name=notes]')?.value || '',
            });
            setLoading(btn, false);
        });

        document.getElementById('btn-add-cred')?.addEventListener('click', () => openModal('add-cred-modal'));

        // Copy buttons in reveal modal
        document.querySelectorAll('.copy-btn[data-target]').forEach(btn => {
            btn.addEventListener('click', () => {
                const val = document.getElementById(btn.dataset.target)?.textContent;
                if (val) copyToClipboard(val, btn.dataset.label || 'Valor');
            });
        });
    }
};

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function timeAgoJs(dt) {
    const diff = (Date.now() - new Date(dt).getTime()) / 1000;
    if (diff < 60)     return 'agora';
    if (diff < 3600)   return Math.floor(diff/60) + ' min';
    if (diff < 86400)  return Math.floor(diff/3600) + 'h';
    return Math.floor(diff/86400) + 'd atrás';
}
