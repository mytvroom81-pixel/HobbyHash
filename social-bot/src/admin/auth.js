const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const config = require('../config');

function verifyPassword(password, hash) {
  return bcrypt.compareSync(password, hash);
}

function requireAuth(req, res, next) {
  if (req.session?.authenticated) {
    return next();
  }
  if (req.path.startsWith('/api/')) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  return res.redirect('/admin/login');
}

function generateCsrfToken(req) {
  if (!req.session.csrfToken) {
    req.session.csrfToken = crypto.randomBytes(32).toString('hex');
  }
  return req.session.csrfToken;
}

function csrfProtection(req, res, next) {
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
    res.locals.csrfToken = generateCsrfToken(req);
    return next();
  }

  const token = req.body?._csrf || req.headers['x-csrf-token'];
  if (!token || token !== req.session?.csrfToken) {
    if (req.path.startsWith('/api/')) {
      return res.status(403).json({ error: 'Invalid CSRF token' });
    }
    return res.status(403).send('Invalid CSRF token');
  }
  res.locals.csrfToken = req.session.csrfToken;
  next();
}

function attemptLogin(username, password) {
  if (username !== config.adminUsername) return false;

  if (process.env.ADMIN_PASSWORD_HASH) {
    return verifyPassword(password, process.env.ADMIN_PASSWORD_HASH);
  }

  if (config.adminPassword) {
    return password === config.adminPassword;
  }

  return false;
}

function hashPassword(password) {
  return bcrypt.hashSync(password, 12);
}

module.exports = {
  requireAuth,
  csrfProtection,
  generateCsrfToken,
  attemptLogin,
  hashPassword,
};
