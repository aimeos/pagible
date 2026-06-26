/**
 * @license MIT, https://opensource.org/license/mit
 */

// graphql-tag's `gql` is the default export, which `export *` does not carry, so
// re-export it explicitly alongside the named exports.
export { default } from 'graphql-tag'
export * from 'graphql-tag'
