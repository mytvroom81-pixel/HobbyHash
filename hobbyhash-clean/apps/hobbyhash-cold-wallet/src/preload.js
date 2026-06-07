const { contextBridge, ipcRenderer, clipboard } = require('electron');
const crypto = require('crypto');
const bip39 = require('bip39');
const bitcoin = require('bitcoinjs-lib');
const { BIP32Factory } = require('bip32');
const ecc = require('tiny-secp256k1');
const QRCode = require('qrcode');
const networkConfig = require('./hobc-network.json');

bitcoin.initEccLib(ecc);
const bip32 = BIP32Factory(ecc);

const SATOSHIS_PER_HOBC = 100000000n;
const STORAGE_VERSION = 1;

const hobcNetwork = {
  messagePrefix: '\x18HobbyHash Coin Signed Message:\n',
  bech32: networkConfig.bech32Hrp,
  bip32: {
    public: networkConfig.bip32.public,
    private: networkConfig.bip32.private
  },
  pubKeyHash: networkConfig.base58Prefixes.pubKeyHash,
  scriptHash: networkConfig.base58Prefixes.scriptHash,
  wif: networkConfig.base58Prefixes.wif
};

function normalizeMnemonic(mnemonic) {
  return String(mnemonic || '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, ' ');
}

function assertMnemonic(mnemonic) {
  const normalized = normalizeMnemonic(mnemonic);
  const words = normalized.split(' ').filter(Boolean);

  if (words.length !== 24) {
    throw new Error('Recovery phrase must contain exactly 24 words.');
  }

  if (!bip39.validateMnemonic(normalized)) {
    throw new Error('Recovery phrase checksum is invalid.');
  }

  return normalized;
}

function rootFromMnemonic(mnemonic) {
  const normalized = assertMnemonic(mnemonic);
  const seed = bip39.mnemonicToSeedSync(normalized);
  return bip32.fromSeed(seed, hobcNetwork);
}

function derivePath(index) {
  const safeIndex = Number(index);
  if (!Number.isInteger(safeIndex) || safeIndex < 0 || safeIndex > 1000000) {
    throw new Error('Address index is invalid.');
  }
  return `${networkConfig.derivation.receivePath}/${safeIndex}`;
}

function deriveAddress(root, index) {
  const path = derivePath(index);
  const child = root.derivePath(path);
  const payment = bitcoin.payments.p2wpkh({
    pubkey: Buffer.from(child.publicKey),
    network: hobcNetwork
  });

  if (!payment.address) {
    throw new Error('Could not derive a receive address.');
  }

  return {
    index,
    path,
    address: payment.address,
    publicKey: Buffer.from(child.publicKey).toString('hex')
  };
}

function parseHobcToSats(value) {
  if (typeof value === 'bigint') {
    return value;
  }

  const raw = String(value ?? '').trim();
  if (!raw || !/^\d+(\.\d{1,8})?$/.test(raw)) {
    throw new Error('Amount must be a positive HOBC value with up to 8 decimals.');
  }

  const [whole, fraction = ''] = raw.split('.');
  return BigInt(whole) * SATOSHIS_PER_HOBC + BigInt(fraction.padEnd(8, '0'));
}

function inputValueToSats(input) {
  if (input.amountSats !== undefined) {
    return BigInt(String(input.amountSats));
  }
  if (input.valueSats !== undefined) {
    return BigInt(String(input.valueSats));
  }
  if (input.satoshis !== undefined) {
    return BigInt(String(input.satoshis));
  }
  if (input.amount !== undefined) {
    return parseHobcToSats(input.amount);
  }
  if (input.value !== undefined) {
    return parseHobcToSats(input.value);
  }
  throw new Error('Each input needs amountSats, valueSats, satoshis, amount, or value.');
}

function outputValueToSats(output) {
  if (output.amountSats !== undefined) {
    return BigInt(String(output.amountSats));
  }
  if (output.valueSats !== undefined) {
    return BigInt(String(output.valueSats));
  }
  if (output.satoshis !== undefined) {
    return BigInt(String(output.satoshis));
  }
  if (output.amount !== undefined) {
    return parseHobcToSats(output.amount);
  }
  if (output.value !== undefined) {
    return parseHobcToSats(output.value);
  }
  throw new Error('Each output needs amountSats, valueSats, satoshis, amount, or value.');
}

function signerFromPath(root, path) {
  const child = root.derivePath(path);
  return {
    publicKey: Buffer.from(child.publicKey),
    sign(hash) {
      return Buffer.from(child.sign(hash));
    }
  };
}

function parseUnsignedPayload(rawText) {
  const text = String(rawText || '').trim();
  if (!text) {
    throw new Error('Unsigned transaction file is empty.');
  }

  try {
    return JSON.parse(text);
  } catch (_error) {
    return { psbtBase64: text };
  }
}

function signPsbtPayload(root, payload) {
  const isHex = typeof payload.psbtHex === 'string' && payload.psbtHex.trim();
  const source = isHex ? payload.psbtHex.trim() : String(payload.psbtBase64 || payload.psbt || '').trim();
  if (!source) {
    throw new Error('PSBT payload is missing.');
  }

  const psbt = isHex
    ? bitcoin.Psbt.fromHex(source, { network: hobcNetwork })
    : bitcoin.Psbt.fromBase64(source, { network: hobcNetwork });

  psbt.signAllInputsHD(root);

  let finalized = false;
  let transactionHex = null;
  try {
    psbt.finalizeAllInputs();
    transactionHex = psbt.extractTransaction().toHex();
    finalized = true;
  } catch (_error) {
    finalized = false;
  }

  return {
    type: finalized ? 'signed-transaction' : 'signed-psbt',
    finalized,
    signedPsbtBase64: psbt.toBase64(),
    transactionHex,
    summary: finalized
      ? 'PSBT signed and finalized locally. Export the transaction hex to an online broadcaster.'
      : 'PSBT signed locally but not finalized. Export the signed PSBT for finalization/broadcast elsewhere.'
  };
}

function signCustomTransaction(root, payload) {
  if (!Array.isArray(payload.inputs) || payload.inputs.length === 0) {
    throw new Error('Custom transaction JSON needs at least one input.');
  }
  if (!Array.isArray(payload.outputs) || payload.outputs.length === 0) {
    throw new Error('Custom transaction JSON needs at least one output.');
  }

  const psbt = new bitcoin.Psbt({ network: hobcNetwork });

  payload.inputs.forEach((input) => {
    if (!input.txid || input.vout === undefined) {
      throw new Error('Each input needs txid and vout.');
    }

    const script = input.scriptPubKey
      ? Buffer.from(String(input.scriptPubKey), 'hex')
      : bitcoin.address.toOutputScript(String(input.address || ''), hobcNetwork);

    psbt.addInput({
      hash: String(input.txid),
      index: Number(input.vout),
      witnessUtxo: {
        script,
        value: inputValueToSats(input)
      }
    });
  });

  payload.outputs.forEach((output) => {
    if (!output.address) {
      throw new Error('Each output needs a destination address.');
    }
    psbt.addOutput({
      address: String(output.address),
      value: outputValueToSats(output)
    });
  });

  payload.inputs.forEach((input, index) => {
    const path = input.path || derivePath(input.addressIndex ?? input.index ?? index);
    psbt.signInput(index, signerFromPath(root, path));
  });

  psbt.finalizeAllInputs();

  return {
    type: 'signed-transaction',
    finalized: true,
    transactionHex: psbt.extractTransaction().toHex(),
    summary: 'Custom unsigned transaction JSON signed and finalized locally. This app does not broadcast.'
  };
}

function encryptMnemonic(mnemonic, password) {
  const normalized = assertMnemonic(mnemonic);
  const passphrase = String(password || '');
  if (passphrase.length < 12) {
    throw new Error('Password must be at least 12 characters.');
  }

  const salt = crypto.randomBytes(16);
  const iv = crypto.randomBytes(12);
  const key = crypto.scryptSync(passphrase, salt, 32, {
    cost: 32768,
    blockSize: 8,
    parallelization: 1,
    maxmem: 64 * 1024 * 1024
  });
  const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
  const plaintext = Buffer.from(JSON.stringify({
    coin: networkConfig.coinName,
    ticker: networkConfig.ticker,
    mnemonic: normalized,
    savedAt: new Date().toISOString()
  }), 'utf8');
  const encrypted = Buffer.concat([cipher.update(plaintext), cipher.final()]);
  const tag = cipher.getAuthTag();

  return JSON.stringify({
    version: STORAGE_VERSION,
    cipher: 'aes-256-gcm',
    kdf: 'scrypt',
    kdfParams: {
      cost: 32768,
      blockSize: 8,
      parallelization: 1,
      salt: salt.toString('base64')
    },
    iv: iv.toString('base64'),
    tag: tag.toString('base64'),
    ciphertext: encrypted.toString('base64')
  }, null, 2);
}

function decryptMnemonic(encryptedPayload, password) {
  const payload = JSON.parse(String(encryptedPayload || ''));
  if (payload.version !== STORAGE_VERSION || payload.cipher !== 'aes-256-gcm' || payload.kdf !== 'scrypt') {
    throw new Error('Encrypted wallet format is not supported.');
  }

  const params = payload.kdfParams || {};
  const salt = Buffer.from(String(params.salt || ''), 'base64');
  const key = crypto.scryptSync(String(password || ''), salt, 32, {
    cost: Number(params.cost || 32768),
    blockSize: Number(params.blockSize || 8),
    parallelization: Number(params.parallelization || 1),
    maxmem: 64 * 1024 * 1024
  });

  const decipher = crypto.createDecipheriv('aes-256-gcm', key, Buffer.from(String(payload.iv || ''), 'base64'));
  decipher.setAuthTag(Buffer.from(String(payload.tag || ''), 'base64'));
  const decrypted = Buffer.concat([
    decipher.update(Buffer.from(String(payload.ciphertext || ''), 'base64')),
    decipher.final()
  ]);
  const wallet = JSON.parse(decrypted.toString('utf8'));
  return assertMnemonic(wallet.mnemonic);
}

contextBridge.exposeInMainWorld('hobcWallet', {
  networkConfig,
  generateMnemonic() {
    return bip39.generateMnemonic(256, crypto.randomBytes);
  },
  validateMnemonic(mnemonic) {
    assertMnemonic(mnemonic);
    return true;
  },
  deriveAddresses(mnemonic, count = 20, start = 0) {
    const root = rootFromMnemonic(mnemonic);
    const safeCount = Math.min(Math.max(Number(count) || 20, 1), 100);
    const safeStart = Math.max(Number(start) || 0, 0);
    return Array.from({ length: safeCount }, (_unused, offset) => deriveAddress(root, safeStart + offset));
  },
  async qrForAddress(address) {
    return QRCode.toDataURL(String(address), {
      errorCorrectionLevel: 'M',
      margin: 1,
      scale: 8,
      color: {
        dark: '#050708',
        light: '#ffd764'
      }
    });
  },
  async signOfflineTransaction(mnemonic, rawText) {
    const root = rootFromMnemonic(mnemonic);
    const payload = parseUnsignedPayload(rawText);
    const signed = payload.psbt || payload.psbtBase64 || payload.psbtHex
      ? signPsbtPayload(root, payload)
      : signCustomTransaction(root, payload);

    return {
      ...signed,
      fileName: signed.finalized ? 'hobc-signed-transaction.json' : 'hobc-signed-psbt.json',
      exportedAt: new Date().toISOString()
    };
  },
  encryptMnemonic,
  decryptMnemonic,
  storageInfo() {
    return ipcRenderer.invoke('storage:get-info');
  },
  readEncryptedWallet() {
    return ipcRenderer.invoke('storage:read-wallet');
  },
  saveEncryptedWallet(encryptedPayload) {
    return ipcRenderer.invoke('storage:save-wallet', encryptedPayload);
  },
  deleteEncryptedWallet() {
    return ipcRenderer.invoke('storage:delete-wallet');
  },
  copyText(text) {
    clipboard.writeText(String(text || ''));
    return true;
  }
});
