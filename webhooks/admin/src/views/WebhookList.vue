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
    last_success_at
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

  inject: ["apollo", "messages"],

  data: () => ({
    dialog: false,
    replaceDialog: false,
    secretDialog: false,
    loading: true,
    saving: false,
    items: [],
    checked: new Set(),
    names: [],
    selected: null,
    url: "",
    events: [],
    status: false,
    secret: "",
  }),

  mounted() {
    this.load();
  },

  methods: {
    async change(callback, failure) {
      if (this.saving) return;
      this.saving = true;

      try {
        await callback();
      } catch (error) {
        this.messages.add(failure + ":\n" + error, "error");
      } finally {
        this.saving = false;
      }
    },

    async load() {
      this.loading = true;
      try {
        const { data } = await this.apollo.query({
          query: LIST,
          fetchPolicy: "network-only",
        });
        this.items = data.cmsWebhooks;
        this.checked = new Set();
        this.names = data.cmsWebhookEvents;
      } catch (error) {
        this.messages.add(
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
      if (!this.events.length || (!this.selected && !this.url.trim()))
        return;

      await this.change(async () => {
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
      }, this.$gettext("Error saving webhook"));
    },

    async replace() {
      if (!this.selected || !this.url.trim()) return;

      await this.change(async () => {
        const { data } = await this.apollo.mutate({
          mutation: REPLACE,
          variables: { id: this.selected.id, url: this.url.trim() },
        });
        this.replaceItem(data.replaceWebhook.webhook);
        this.replaceDialog = false;
        this.showSecret(data.replaceWebhook.secret);
      }, this.$gettext("Error replacing webhook destination"));
    },

    async rotate(item) {
      await this.change(async () => {
        const { data } = await this.apollo.mutate({
          mutation: ROTATE,
          variables: { id: item.id },
        });
        this.replaceItem(data.rotateWebhook.webhook);
        this.showSecret(data.rotateWebhook.secret);
      }, this.$gettext("Error rotating webhook secret"));
    },

    async remove(item = null) {
      const ids = item ? [item.id] : [...this.checked];
      const question = item
        ? this.$gettext("Delete this webhook?")
        : `${this.$gettext("Delete")} (${ids.length})?`;

      if (this.saving || !ids.length || !window.confirm(question))
        return;

      await this.change(async () => {
        await this.apollo.mutate({
          mutation: DROP,
          variables: { id: ids },
        });
        const removed = new Set(ids);
        this.items = this.items.filter((entry) => !removed.has(entry.id));
        this.checked = new Set(
          [...this.checked].filter((id) => !removed.has(id)),
        );
      }, this.$gettext("Error deleting webhook"));
    },

    async copySecret() {
      try {
        await navigator.clipboard.writeText(this.secret);
        this.messages.add(this.$gettext("Secret copied"), "success");
      } catch (_error) {
        this.messages.add(this.$gettext("Unable to copy secret"), "error");
      }
    },

    errorText(item) {
      if (!item.last_error) return this.$gettext("None");
      const status = item.last_error.status
        ? ` (${item.last_error.status})`
        : "";
      const reasons = {
        destination_not_allowed: this.$gettext("Access denied"),
        invalid_header: this.$gettext("Value has invalid format"),
        invalid_url: this.$gettext("Not a valid URL"),
      };
      return `${reasons[item.last_error.reason] || this.$gettext("Delivery failed")}${status}`;
    },

    successText(item) {
      return item.last_success_at
        ? new Date(item.last_success_at).toLocaleString(
            this.$vuetify.locale.current,
          )
        : this.$gettext("None");
    },

    toggle() {
      this.checked = this.checked.size
        ? new Set()
        : new Set(this.items.map((item) => item.id));
    },

    toggleCheck(item) {
      const checked = new Set(this.checked);

      if (checked.has(item.id)) checked.delete(item.id);
      else checked.add(item.id);

      this.checked = checked;
    },

    replaceItem(item) {
      const index = this.items.findIndex((entry) => entry.id === item.id);
      if (index >= 0) this.items.splice(index, 1, item);
    },

    showSecret(secret) {
      this.secret = secret;
      this.secretDialog = true;
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
        <v-btn
          v-if="checked.size"
          color="error"
          variant="text"
          :disabled="saving"
          @click="remove()"
        >
          {{ $gettext("Delete") }} ({{ checked.size }})
        </v-btn>
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
            <th>
              <v-checkbox-btn
                :model-value="checked.size > 0"
                @click.stop="toggle"
                :aria-label="$gettext('Toggle selection')"
              />
            </th>
            <th>{{ $gettext("Endpoint") }}</th>
            <th>{{ $gettext("Events") }}</th>
            <th>{{ $gettext("Status") }}</th>
            <th>{{ $gettext("Failures") }}</th>
            <th>{{ $gettext("Last success") }}</th>
            <th>{{ $gettext("Last error") }}</th>
            <th class="text-end">{{ $gettext("Actions") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>
              <v-checkbox-btn
                :model-value="checked.has(item.id)"
                @update:model-value="toggleCheck(item)"
                :aria-label="$gettext('Toggle selection')"
              />
            </td>
            <td>{{ item.endpoint }}</td>
            <td>{{ item.events.join(", ") }}</td>
            <td>
              <v-chip :color="item.status ? 'success' : undefined" size="small">
                {{ item.status ? $gettext("Active") : $gettext("Inactive") }}
              </v-chip>
            </td>
            <td>{{ item.failures }}</td>
            <td>{{ successText(item) }}</td>
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

  </div>
</template>
