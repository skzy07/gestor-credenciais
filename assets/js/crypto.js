/**
 * VaultCrypto — Módulo de criptografia E2EE
 * RSA-OAEP 4096 + AES-256-GCM + PBKDF2-SHA256
 * Toda a desencriptação acontece no browser. O servidor nunca vê dados em plaintext.
 */
const VaultCrypto = (() => {
    'use strict';

    const RSA_ALGO = {
        name: 'RSA-OAEP',
        modulusLength: 4096,
        publicExponent: new Uint8Array([1, 0, 1]),
        hash: 'SHA-256'
    };
    const AES_NAME       = 'AES-GCM';
    const AES_LENGTH     = 256;
    const PBKDF2_ITER    = 310000;
    const PBKDF2_HASH    = 'SHA-256';
    const IV_LEN         = 12;   // bytes — AES-GCM 96-bit IV
    const SALT_LEN       = 32;   // bytes — PBKDF2 256-bit salt

    // ── Utilitários ──────────────────────────────────────
    const rnd   = n => crypto.getRandomValues(new Uint8Array(n));
    const enc   = new TextEncoder();
    const dec   = new TextDecoder();

    const toB64 = buf => {
        const u8 = new Uint8Array(buf instanceof ArrayBuffer ? buf : buf.buffer);
        let s = '';
        for (let i = 0; i < u8.length; i++) s += String.fromCharCode(u8[i]);
        return btoa(s);
    };

    const fromB64 = b64 => {
        const s = atob(b64);
        const u8 = new Uint8Array(s.length);
        for (let i = 0; i < s.length; i++) u8[i] = s.charCodeAt(i);
        return u8.buffer;
    };

    // ── Derivação de Chave (PBKDF2) ───────────────────────
    async function deriveAESFromPassword(password, saltB64) {
        const saltBuf = fromB64(saltB64);
        const base = await crypto.subtle.importKey(
            'raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']
        );
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt: saltBuf, iterations: PBKDF2_ITER, hash: PBKDF2_HASH },
            base,
            { name: AES_NAME, length: AES_LENGTH },
            false, ['encrypt', 'decrypt']
        );
    }

    // ── AES-GCM Encrypt / Decrypt ─────────────────────────
    async function aesEncrypt(plaintext, aesKey) {
        const iv  = rnd(IV_LEN);
        const ct  = await crypto.subtle.encrypt(
            { name: AES_NAME, iv },
            aesKey,
            typeof plaintext === 'string' ? enc.encode(plaintext) : plaintext
        );
        return { ciphertext: toB64(ct), iv: toB64(iv) };
    }

    async function aesDecrypt(ciphertextB64, ivB64, aesKey) {
        const plain = await crypto.subtle.decrypt(
            { name: AES_NAME, iv: new Uint8Array(fromB64(ivB64)) },
            aesKey,
            fromB64(ciphertextB64)
        );
        return dec.decode(plain);
    }

    // ── API Pública ───────────────────────────────────────
    return {

        /** Gera keypair RSA-4096 */
        async generateKeyPair() {
            return crypto.subtle.generateKey(RSA_ALGO, true, ['encrypt', 'decrypt']);
        },

        /** Exporta chave pública como JWK string */
        async exportPublicKey(pubKey) {
            return JSON.stringify(await crypto.subtle.exportKey('jwk', pubKey));
        },

        /** Exporta chave privada como JWK string */
        async exportPrivateKey(privKey) {
            return JSON.stringify(await crypto.subtle.exportKey('jwk', privKey));
        },

        /** Importa chave pública de JWK string */
        async importPublicKey(jwkStr) {
            const jwk = typeof jwkStr === 'string' ? JSON.parse(jwkStr) : jwkStr;
            return crypto.subtle.importKey('jwk', jwk, RSA_ALGO, false, ['encrypt']);
        },

        /** Importa chave privada de JWK string */
        async importPrivateKey(jwkStr) {
            const jwk = typeof jwkStr === 'string' ? JSON.parse(jwkStr) : jwkStr;
            return crypto.subtle.importKey('jwk', jwk, RSA_ALGO, true, ['decrypt']);
        },

        /**
         * Encripta a chave privada com a password do utilizador (PBKDF2 → AES-256-GCM)
         * Retorna: { encryptedPrivateKey, iv, salt } — tudo em base64
         */
        async encryptPrivateKeyWithPassword(privKey, password) {
            const salt   = rnd(SALT_LEN);
            const saltB64 = toB64(salt);
            const aesKey  = await deriveAESFromPassword(password, saltB64);
            const privJwk = await this.exportPrivateKey(privKey);
            const { ciphertext, iv } = await aesEncrypt(privJwk, aesKey);
            return { encryptedPrivateKey: ciphertext, iv, salt: saltB64 };
        },

        /**
         * Desencripta a chave privada com a password
         */
        async decryptPrivateKeyWithPassword(encryptedPrivKeyB64, ivB64, saltB64, password) {
            const aesKey  = await deriveAESFromPassword(password, saltB64);
            const privJwk = await aesDecrypt(encryptedPrivKeyB64, ivB64, aesKey);
            return this.importPrivateKey(privJwk);
        },

        /**
         * Encripta a chave privada com o código de recuperação (mesmo processo)
         */
        async encryptPrivateKeyWithRecovery(privKey, recoveryCode) {
            return this.encryptPrivateKeyWithPassword(privKey, recoveryCode);
        },

        /**
         * Desencripta a chave privada com o código de recuperação
         */
        async decryptPrivateKeyWithRecovery(encryptedB64, ivB64, saltB64, recoveryCode) {
            return this.decryptPrivateKeyWithPassword(encryptedB64, ivB64, saltB64, recoveryCode);
        },

        /** Gera chave AES-256 aleatória para uma credencial */
        async generateCredentialKey() {
            return crypto.subtle.generateKey(
                { name: AES_NAME, length: AES_LENGTH }, true, ['encrypt', 'decrypt']
            );
        },

        /**
         * Encripta dados de credencial com uma AES key
         * data = { label_internal, username, password, url, notes }
         */
        async encryptCredential(data, aesKey) {
            return aesEncrypt(JSON.stringify(data), aesKey);
        },

        /** Desencripta dados de credencial */
        async decryptCredential(ciphertextB64, ivB64, aesKey) {
            const json = await aesDecrypt(ciphertextB64, ivB64, aesKey);
            return JSON.parse(json);
        },

        /**
         * Encripta a AES key com a RSA pública de um utilizador
         * → O que guardamos em credential_keys.encrypted_aes_key
         */
        async encryptCredentialKey(aesKey, rsaPublicKey) {
            const raw = await crypto.subtle.exportKey('raw', aesKey);
            const enc = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, rsaPublicKey, raw);
            return toB64(enc);
        },

        /**
         * Desencripta a AES key com a RSA privada
         */
        async decryptCredentialKey(encryptedKeyB64, rsaPrivateKey) {
            const raw = await crypto.subtle.decrypt(
                { name: 'RSA-OAEP' }, rsaPrivateKey, fromB64(encryptedKeyB64)
            );
            return crypto.subtle.importKey(
                'raw', raw, { name: AES_NAME }, true, ['encrypt', 'decrypt']
            );
        },

        /**
         * Re-encripta a AES key de um utilizador para outro
         * Usado ao aprovar acesso: decrypt com privKey de B → encrypt com pubKey de A
         */
        async reEncryptCredentialKey(encryptedKeyB64, sourcePrivKey, targetPubKey) {
            const raw = await crypto.subtle.decrypt(
                { name: 'RSA-OAEP' }, sourcePrivKey, fromB64(encryptedKeyB64)
            );
            const reenc = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, targetPubKey, raw);
            return toB64(reenc);
        },

        /** Gera código de recuperação (32 chars hex uppercase) */
        generateRecoveryCode() {
            const bytes = rnd(16);
            return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('').toUpperCase();
        },

        // ── Gestão de Sessão ─────────────────────────────
        async savePrivateKeyToSession(privKey) {
            const jwk = await this.exportPrivateKey(privKey);
            sessionStorage.setItem('vk_pk', jwk);
        },

        async loadPrivateKeyFromSession() {
            const jwk = sessionStorage.getItem('vk_pk');
            if (!jwk) return null;
            try { return await this.importPrivateKey(jwk); } catch { return null; }
        },

        clearSession() {
            sessionStorage.removeItem('vk_pk');
            sessionStorage.removeItem('vk_user');
        },

        saveUserToSession(user) {
            sessionStorage.setItem('vk_user', JSON.stringify(user));
        },

        getUserFromSession() {
            try { return JSON.parse(sessionStorage.getItem('vk_user')); } catch { return null; }
        },

        // ── Validação de NIF (cliente) ───────────────────
        validateNIF(nif) {
            nif = String(nif).trim();
            if (!/^\d{9}$/.test(nif)) return { valid: false, error: 'O NIF deve ter 9 dígitos.' };
            if (!['1','2','3','5','6','7','8','9'].includes(nif[0]))
                return { valid: false, error: 'NIF inválido (primeiro dígito incorreto).' };
            /*
            let sum = 0;
            for (let i = 0; i < 8; i++) sum += parseInt(nif[i]) * (9 - i);
            const rem = sum % 11;
            const check = rem < 2 ? 0 : 11 - rem;
            if (check !== parseInt(nif[8])) return { valid: false, error: 'NIF inválido (dígito de controlo).' };
            */
            return { valid: true, error: null };
        },

        // ── Exports internos ─────────────────────────────
        toB64,
        fromB64
    };
})();
