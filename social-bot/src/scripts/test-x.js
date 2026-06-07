const { runPlatformTest } = require('./test-platform');

runPlatformTest('x').catch((err) => {
  console.error(err.message);
  process.exit(1);
});
