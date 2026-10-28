import { defineConfig } from 'cypress'

export default defineConfig({
  allowCypressEnv: false,
  e2e: {
    baseUrl: 'http://localhost:4173',
    specPattern: 'cypress/e2e/**/*.cy.{js,ts}',
  },
  component: {
    devServer: {
      framework: 'vue',
      bundler: 'vite',
    },
    specPattern: [
      'cypress/unit/**/*.cy.{js,ts}',
      // admin plugins of the packages share the admin's component-test setup
      '../*/admin/cypress/unit/**/*.cy.{js,ts}',
    ],
  },
})
