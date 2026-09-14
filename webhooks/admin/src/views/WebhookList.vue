<!-- @license MIT, https://opensource.org/license/mit -->

<script>
import gql from "graphql-tag";

const FIELDS = gql`
  fragment CmsWebhookFields on CmsWebhook {
    id
    status
    failures
    endpoint
    events
    last_error
  }
`;

const LIST = gql`
  query CmsWebhooks {
    cmsWebhooks {
      ...CmsWebhookFields
    }
    cmsWebhookEvents
  }
  ${FIELDS}
`;

const ADD = gql`
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

const SAVE = gql`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${FIELDS}
`;

const REPLACE = gql`
  mutation ReplaceWebhook($id: ID!, $url: String!) {
    replaceWebhook(id: $id, url: $url) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${FIELDS}
`;

const ROTATE = gql`
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

const DROP = gql`
  mutation DropWebhook($id: [ID!]!) {
    dropWebhook(id: $id)
  }
`;

export default {
  name: "WebhookList",

  inject: ["apollo"],

  props: {
    panel: {
      type: Object,
      required: true,
    },
  },

  data: () => ({
    dialog: false,
    replaceDialog: false,
    secretDialog: false,
    loading: true,
    saving: false,
    items: [],
    names: [],
    selected: null,
    url: "",
    events: [],
    status: false,
    secret: "",
    message: "",
    messageColor: "info",
    messageOpen: false,
  }),

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const { data } = await this.apollo.query({
          query: LIST,
          fetchPolicy: "network-only",
        });
        this.items = data.cmsWebhooks;
        this.names = data.cmsWebhookEvents;
      } catch (error) {
        this.notify(
          this.$gettext("Error fetching webhooks") + ":\n" + error,
          "error",
        );
      } finally {
        this.loading = false;
      }
    },

    openAdd() {
      this.selected = null;
      this.url = "";
      this.events = [];
      this.status = false;
      this.dialog = true;
    },

    openEdit(item) {
      this.selected = item;
      this.events = [...item.events];
      this.status = item.status;
      this.dialog = true;
    },

    openReplace(item) {
      this.selected = item;
      this.url = "";
      this.replaceDialog = true;
    },

    async save() {
      if (
        this.saving ||
        !this.events.length ||
        (!this.selected && !this.url.trim())
      )
        return;
      this.saving = true;

      try {
        if (this.selected) {
          const { data } = await this.apollo.mutate({
            mutation: SAVE,
            variables: {
              id: this.selected.id,
              input: { events: this.events, status: this.status },
            },
          });
          this.replaceItem(data.saveWebhook);
        } else {
          const { data } = await this.apollo.mutate({
            mutation: ADD,
            variables: { input: { url: this.url.trim(), events: this.events } },
          });
          this.items.unshift(data.addWebhook.webhook);
          this.showSecret(data.addWebhook.secret);
        }
        this.dialog = false;
      } catch (error) {
        this.notify(
          this.$gettext("Error saving webhook") + ":\n" + error,
          "error",
        );
      } finally {
        this.saving = false;
      }
    },

    async replace() {
      if (this.saving || !this.selected || !this.url.trim()) return;
      this.saving = true;

      try {
        const { data } = await this.apollo.mutate({
          mutation: REPLACE,
          variables: { id: this.selected.id, url: this.url.trim() },
        });
        this.replaceItem(data.replaceWebhook.webhook);
        this.replaceDialog = false;
        this.showSecret(data.replaceWebhook.secret);
      } catch (error) {
        this.notify(
          this.$gettext("Error replacing webhook destination") + ":\n" + error,
          "error",
        );
      } finally {
        this.saving = false;
      }
    },

    async rotate(item) {
      if (this.saving) return;
      this.saving = true;

      try {
        const { data } = await this.apollo.mutate({
          mutation: ROTATE,
          variables: { id: item.id },
        });
        this.replaceItem(data.rotateWebhook.webhook);
        this.showSecret(data.rotateWebhook.secret);
      } catch (error) {
        this.notify(
          this.$gettext("Error rotating webhook secret") + ":\n" + error,
          "error",
        );
      } finally {
        this.saving = false;
      }
    },

    async remove(item) {
      if (this.saving || !window.confirm(this.$gettext("Delete this webhook?")))
        return;
      this.saving = true;

      try {
        await this.apollo.mutate({
          mutation: DROP,
          variables: { id: [item.id] },
        });
        this.items = this.items.filter((entry) => entry.id !== item.id);
      } catch (error) {
        this.notify(
          this.$gettext("Error deleting webhook") + ":\n" + error,
          "error",
        );
      } finally {
        this.saving = false;
      }
    },

    async copySecret() {
      try {
        await navigator.clipboard.writeText(this.secret);
        this.notify(this.$gettext("Secret copied"), "success");
      } catch (_error) {
        this.notify(this.$gettext("Unable to copy secret"), "error");
      }
    },

    errorText(item) {
      if (!item.last_error) return this.$gettext("None");
      const status = item.last_error.status
        ? ` (${item.last_error.status})`
        : "";
      const reasons = {
        delivery_failed: this.$gettext("Delivery failed"),
        destination_not_allowed: this.$gettext("Access denied"),
        http_error: this.$gettext("Delivery failed"),
        invalid_header: this.$gettext("Value has invalid format"),
        invalid_url: this.$gettext("Not a valid URL"),
        resolution_failed: this.$gettext("Delivery failed"),
        response_body_too_large: this.$gettext("Delivery failed"),
        response_headers_too_large: this.$gettext("Delivery failed"),
        transport_error: this.$gettext("Delivery failed"),
        transport_unavailable: this.$gettext("Delivery failed"),
      };
      return `${reasons[item.last_error.reason] || this.$gettext("Delivery failed")}${status}`;
    },

    replaceItem(item) {
      const index = this.items.findIndex((entry) => entry.id === item.id);
      if (index >= 0) this.items.splice(index, 1, item);
    },

    showSecret(secret) {
      this.secret = secret;
      this.secretDialog = true;
    },

    notify(text, color) {
      this.message = text;
      this.messageColor = color;
      this.messageOpen = true;
    },
  },
};
</script>

<template>
  <div class="webhook-list">
    <v-container fluid class="pa-4 pa-md-6">
      <div class="d-flex align-center ga-3 mb-5">
        <p class="text-medium-emphasis mb-0">
          {{
            $gettext(
              "Send signed notifications when published content changes.",
            )
          }}
        </p>
        <v-spacer />
        <v-btn color="primary" @click="openAdd">{{
          $gettext("Add webhook")
        }}</v-btn>
      </div>

      <v-progress-linear v-if="loading" indeterminate />
      <v-alert v-else-if="!items.length" type="info" variant="tonal">
        {{ $gettext("No webhooks configured.") }}
      </v-alert>
      <v-table v-else>
        <thead>
          <tr>
            <th>{{ $gettext("Endpoint") }}</th>
            <th>{{ $gettext("Events") }}</th>
            <th>{{ $gettext("Status") }}</th>
            <th>{{ $gettext("Failures") }}</th>
            <th>{{ $gettext("Last error") }}</th>
            <th class="text-end">{{ $gettext("Actions") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ item.endpoint }}</td>
            <td>{{ item.events.join(", ") }}</td>
            <td>
              <v-chip :color="item.status ? 'success' : undefined" size="small">
                {{ item.status ? $gettext("Active") : $gettext("Inactive") }}
              </v-chip>
            </td>
            <td>{{ item.failures }}</td>
            <td>{{ errorText(item) }}</td>
            <td class="text-end text-no-wrap">
              <v-btn variant="text" size="small" @click="openEdit(item)">{{
                $gettext("Edit")
              }}</v-btn>
              <v-btn variant="text" size="small" @click="openReplace(item)">{{
                $gettext("Replace")
              }}</v-btn>
              <v-btn variant="text" size="small" @click="rotate(item)">{{
                $gettext("Rotate")
              }}</v-btn>
              <v-btn
                variant="text"
                size="small"
                color="error"
                @click="remove(item)"
                >{{ $gettext("Delete") }}</v-btn
              >
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-container>

    <v-dialog v-model="dialog" max-width="640">
      <v-card>
        <v-card-title>{{
          selected ? $gettext("Edit webhook") : $gettext("Add webhook")
        }}</v-card-title>
        <v-card-text>
          <v-text-field
            v-if="!selected"
            v-model="url"
            :label="$gettext('HTTPS endpoint URL')"
            maxlength="500"
            autofocus
          />
          <v-select
            v-model="events"
            :items="names"
            :label="$gettext('Events')"
            multiple
            chips
          />
          <v-switch
            v-if="selected"
            v-model="status"
            color="success"
            :label="$gettext('Active')"
          />
          <v-alert v-else type="info" variant="tonal">
            {{
              $gettext(
                "New webhooks are inactive until you save them as active.",
              )
            }}
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="dialog = false">{{ $gettext("Cancel") }}</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">{{
            $gettext("Save")
          }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="replaceDialog" max-width="640">
      <v-card>
        <v-card-title>{{
          $gettext("Replace webhook destination")
        }}</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="url"
            :label="$gettext('HTTPS endpoint URL')"
            maxlength="500"
            autofocus
          />
          <v-alert type="warning" variant="tonal">
            {{
              $gettext(
                "Replacing the destination rotates the secret and disables the webhook.",
              )
            }}
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="replaceDialog = false">{{ $gettext("Cancel") }}</v-btn>
          <v-btn color="primary" :loading="saving" @click="replace">{{
            $gettext("Replace")
          }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="secretDialog" max-width="640" persistent>
      <v-card>
        <v-card-title>{{ $gettext("Webhook secret") }}</v-card-title>
        <v-card-text>
          <v-alert type="warning" variant="tonal" class="mb-4">
            {{ $gettext("Copy this secret now. It will not be shown again.") }}
          </v-alert>
          <v-text-field :model-value="secret" readonly />
        </v-card-text>
        <v-card-actions>
          <v-btn color="primary" @click="copySecret">{{
            $gettext("Copy secret")
          }}</v-btn>
          <v-spacer />
          <v-btn
            @click="
              secretDialog = false;
              secret = '';
            "
            >{{ $gettext("Done") }}</v-btn
          >
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="messageOpen" :color="messageColor">{{
      message
    }}</v-snackbar>
  </div>
</template>
