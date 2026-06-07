const { runPlatformTest } = require('./test-platform');

runPlatformTest('facebook').catch((err) => {
  console.error(err.message);
  process.exit(1);
});
