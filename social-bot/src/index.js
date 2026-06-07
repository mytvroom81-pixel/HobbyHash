require('dotenv').config();

const express = require('express');
const session = require('express-session');
const cookieParser = require('cookie-parser');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const path = require('path');

const config = require('./config');
const logger = require('./logger');
const { migrate } = require('./scripts/migrate');
const { closeDb, auditLog } = require('./db');
const { startScheduler } = require('./scheduler');
const { startReplyListeners } = require('./replies/replyRouter');
const { createAdminRouter } = require('./admin/routes');
const { createInternalRouter } = require('./api/internal');
const { generateCsrfToken } = require('./admin/auth');

function main() {
  migrate();

  const app = express();

  app.set('view engine', 'ejs');
  app.set('views', path.join(__dirname, 'admin', 'views'));
  app.set('trust proxy', 1);

  app.use(helmet({
    contentSecurityPolicy: false,
  }));

  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));
  app.use(cookieParser());

  app.use(session({
    secret: config.sessionSecret,
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      secure: config.nodeEnv === 'production',
      sameSite: 'lax',
      maxAge: 12 * 60 * 60 * 1000,
    },
  }));

  app.use('/admin/static', express.static(path.join(__dirname, 'admin', 'static')));

  app.use((req, res, next) => {
    res.locals.csrfToken = req.session?.csrfToken || generateCsrfToken(req);
    next();
  });

  const adminLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 200,
    message: 'Too many requests',
  });

  app.use('/internal', createInternalRouter());

  const adminEnabled = process.env.SOCIAL_BOT_STANDALONE_ADMIN !== 'false';
  if (adminEnabled) {
    app.use('/admin', adminLimiter, createAdminRouter());
  }

  app.get('/health', (req, res) => {
    res.json({ ok: true, service: 'hobbyhash-social-bot', time: new Date().toISOString() });
  });

  app.get('/', (req, res) => {
    if (adminEnabled) {
      return res.redirect('/admin/');
    }
    res.json({ ok: true, service: 'hobbyhash-social-bot', admin: 'use HOBC PHP admin at /admin/social-bot.php' });
  });

  const server = app.listen(config.port, () => {
    logger.info('HobbyHash Social Bot started', {
      port: config.port,
      env: config.nodeEnv,
      dryRun: require('./utils/dryRun').isDryRun(),
    });
    auditLog('service_started', { port: config.port });
  });

  startScheduler();
  const stopReplies = startReplyListeners();

  const shutdown = (signal) => {
    logger.info('Shutting down', { signal });
    if (stopReplies) stopReplies();
    server.close(() => {
      closeDb();
      process.exit(0);
    });
  };

  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));
}

main();
