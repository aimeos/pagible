/** @license MIT, https://opensource.org/license/mit */

import gql from "graphql-tag";

const FIELDS = gql`
  fragment CmsWebhookFields on CmsWebhook {
    id
    status
    name
    endpoint
    events
    last_error {
      reason
      status
      at
    }
    last_success_at
    paused_until
  }
`;

export const LIST = gql`
  query CmsWebhooks {
    cmsWebhooks {
      ...CmsWebhookFields
    }
    cmsWebhookEvents
    cmsWebhookServer {
      enabled
      blocked
    }
  }
  ${FIELDS}
`;

export const ADD = gql`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${FIELDS}
`;

export const SAVE = gql`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${FIELDS}
`;

export const ROTATE = gql`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${FIELDS}
`;

export const PING = gql`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
`;

export const PURGE = gql`
  mutation PurgeWebhook($id: [ID!]!) {
    purgeWebhook(id: $id)
  }
`;
