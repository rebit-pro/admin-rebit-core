const baseUrl = process.env.E2E_BASE_URL?.trim() || 'http://127.0.0.1:4173';

export default {
  paths: ['e2e/features/**/*.feature'],
  import: ['e2e/support/**/*.ts', 'e2e/steps/**/*.ts'],
  loader: ['ts-node/esm'],
  format: ['progress-bar', 'html:reports/e2e/cucumber-report.html'],
  publishQuiet: true,
  parallel: 1,
  worldParameters: {
    baseUrl
  }
};
