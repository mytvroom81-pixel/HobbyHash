const { runPlatformTest } = require('./test-platform');

runPlatformTest('discord').catch((err) => {
  console.error(err.message);
  process.exit(1);
});
