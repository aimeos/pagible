<!-- @license MIT, https://opensource.org/license/mit -->

<script>
import gql from "graphql-tag";
import {
  mdiDelete,
  mdiDotsVertical,
  mdiKeyVariant,
  mdiLinkVariant,
  mdiMagnify,
  mdiPencil,
  mdiPlus,
  mdiRefresh,
  mdiSend,
} from "@mdi/js";

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

const LIST = gql`
  query CmsWebhooks {
    cmsWebhooks {
      ...CmsWebhookFields
    }
    cmsWebhookEvents
    cmsWebhookServer {
      enabled
      blocked
      stalled_since
    }
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

const PING = gql`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
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
    term: "",
    statusFilter: null,
    url: "",
    name: "",
    events: [],
    status: false,
    secret: "",
    server: { enabled: true, blocked: null, stalled_since: null },
  }),

  setup() {
    return {
      mdiDelete,
      mdiDotsVertical,
      mdiKeyVariant,
      mdiLinkVariant,
      mdiMagnify,
      mdiPencil,
      mdiPlus,
      mdiRefresh,
      mdiSend,
    };
  },

  computed: {
    serverText() {
      if (!this.server.enabled) {
        return this.$pgettext(
          "webhooks",
          "Webhooks are disabled by the server configuration, no events are sent",
        );
      }

      if (this.server.blocked) {
        return this.$pgettext(
          "webhooks",
          "All deliveries are blocked by the server configuration",
        );
      }

      if (this.server.stalled_since) {
        return this.$pgettext(
          "webhooks",
          "No queued delivery was processed since %{date}, check the queue worker",
          { date: this.dateText(this.server.stalled_since) },
        );
      }

      return "";
    },

    filtered() {
      const term = (this.term ?? "").trim().toLocaleLowerCase();

      return this.items.filter((item) => {
        if (this.statusFilter !== null && item.status !== this.statusFilter)
          return false;

        return (
          !term ||
          item.name.toLocaleLowerCase().includes(term) ||
          item.endpoint.toLocaleLowerCase().includes(term) ||
          item.events.some((event) => event.toLocaleLowerCase().includes(term))
        );
      });
    },

    statusItems() {
      return [
        { title: this.$pgettext("webhooks", "All"), value: null },
        { title: this.$pgettext("webhooks", "Active"), value: true },
        { title: this.$pgettext("webhooks", "Inactive"), value: false },
      ];
    },
  },

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

    closeSecret() {
      this.secretDialog = false;
      this.secret = "";
    },

    dateText(value) {
      return new Date(value).toLocaleString(this.$vuetify.locale.current);
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
        this.server = data.cmsWebhookServer || {
          enabled: true,
          blocked: null,
          stalled_since: null,
        };
      } catch (error) {
        this.messages.add(
          this.$pgettext("webhooks", "Error fetching webhooks") + ":\n" + error,
          "error",
        );
      } finally {
        this.loading = false;
      }
    },

    openAdd() {
      this.selected = null;
      this.url = "";
      this.name = "";
      this.events = [];
      this.status = false;
      this.secret = "";
      this.dialog = true;
    },

    openEdit(item) {
      this.selected = item;
      this.name = item.name;
      this.events = [...item.events];
      this.status = item.status;
      this.secret = "";
      this.dialog = true;
    },

    openReplace(item) {
      this.selected = item;
      this.url = "";
      this.replaceDialog = true;
    },

    async save() {
      if (!this.events.length || (!this.selected && !this.validUrl(this.url)))
        return;

      await this.change(
        async () => {
          if (this.selected) {
            const { data } = await this.apollo.mutate({
              mutation: SAVE,
              variables: {
                id: this.selected.id,
                input: {
                  name: this.name.trim(),
                  events: this.events,
                  status: this.status,
                },
              },
            });
            this.put(data.saveWebhook);
            this.dialog = false;
          } else {
            const { data } = await this.apollo.mutate({
              mutation: ADD,
              variables: {
                input: {
                  url: this.url.trim(),
                  name: this.name.trim(),
                  events: this.events,
                  status: this.status,
                },
              },
            });
            // keep the dialog open to show the one-time secret
            this.put(data.addWebhook.webhook);
            this.secret = data.addWebhook.secret;
          }
        },
        this.$pgettext("webhooks", "Error saving webhook"),
      );
    },

    async replace() {
      if (!this.selected || !this.validUrl(this.url)) return;

      await this.change(
        async () => {
          const { data } = await this.apollo.mutate({
            mutation: REPLACE,
            variables: { id: this.selected.id, url: this.url.trim() },
          });
          this.replaceDialog = false;
          this.provision(data.replaceWebhook);
        },
        this.$pgettext("webhooks", "Error replacing webhook destination"),
      );
    },

    async rotate(item) {
      const question = this.$pgettext(
        "webhooks",
        "Rotate the secret of this webhook? Receivers must be updated with the new secret.",
      );

      if (this.saving || !window.confirm(`${question}\n\n${this.label(item)}`))
        return;

      await this.change(
        async () => {
          const { data } = await this.apollo.mutate({
            mutation: ROTATE,
            variables: { id: item.id },
          });
          this.provision(data.rotateWebhook);
        },
        this.$pgettext("webhooks", "Error rotating webhook secret"),
      );
    },

    async ping(item) {
      await this.change(
        async () => {
          const { data } = await this.apollo.mutate({
            mutation: PING,
            variables: { id: item.id },
          });
          const result = data.pingWebhook;

          if (result.success) {
            // a successful test event resumes paused deliveries
            this.items = this.items.map((entry) =>
              entry.id === item.id ? { ...entry, paused_until: null } : entry,
            );
            this.messages.add(
              this.$pgettext("webhooks", "Test event delivered") +
                ` (${result.status})`,
              "success",
            );
          } else {
            this.messages.add(
              this.$pgettext("webhooks", "Test event failed") +
                ": " +
                this.reasonText(result.reason, result.status),
              "error",
            );
          }
        },
        this.$pgettext("webhooks", "Test event failed"),
      );
    },

    async remove(item = null) {
      const ids = item ? [item.id] : [...this.checked];
      const question = item
        ? `${this.$pgettext("webhooks", "Delete this webhook?")}\n\n${this.label(item)}`
        : `${this.$pgettext("webhooks", "Delete")} (${ids.length})?`;

      if (this.saving || !ids.length || !window.confirm(question)) return;

      await this.change(
        async () => {
          // the server deletes up to 100 webhooks at once, see dropWebhook in the GraphQL schema
          for (let i = 0; i < ids.length; i += 100) {
            const removed = new Set(ids.slice(i, i + 100));

            await this.apollo.mutate({
              mutation: DROP,
              variables: { id: [...removed] },
            });
            this.items = this.items.filter((entry) => !removed.has(entry.id));
            this.checked = new Set(
              [...this.checked].filter((id) => !removed.has(id)),
            );
          }
        },
        this.$pgettext("webhooks", "Error deleting webhook"),
      );
    },

    async copySecret() {
      try {
        await navigator.clipboard.writeText(this.secret);
        this.messages.add(
          this.$pgettext("webhooks", "Secret copied"),
          "success",
        );
      } catch (_error) {
        this.messages.add(
          this.$pgettext("webhooks", "Unable to copy secret"),
          "error",
        );
      }
    },

    errorText(item) {
      const error = item.last_error;

      if (!error) return this.$pgettext("webhooks", "None");

      const text = this.reasonText(error.reason, error.status);
      return error.at ? `${text} · ${this.dateText(error.at)}` : text;
    },

    // endpoints only contain the host, so the name tells webhooks apart
    label(item) {
      return item.name ? `${item.name} · ${item.endpoint}` : item.endpoint;
    },

    reasonText(reason, status) {
      const reasons = {
        connection_failed: this.$pgettext("webhooks", "Connection failed"),
        destination_not_allowed: this.$pgettext("webhooks", "Access denied"),
        invalid_encryption: this.$pgettext("webhooks", "Can't be decrypted, replace the URL"),
        invalid_policy: this.$pgettext("webhooks", "Blocked by server configuration"),
        invalid_url: this.$pgettext("webhooks", "Not a valid URL"),
        queue_failed: this.$pgettext("webhooks", "Queue unavailable"),
        resolution_failed: this.$pgettext("webhooks", "Host not found"),
        response_headers_too_large: this.$pgettext("webhooks", "Response too large"),
        timeout: this.$pgettext("webhooks", "Request timed out"),
        tls_error: this.$pgettext("webhooks", "Secure connection failed"),
      };
      // redirects are rejected to prevent forwarding deliveries to other hosts
      const redirect = reason === "http_error" && status >= 300 && status < 400;
      const text = redirect
        ? this.$pgettext("webhooks", "Redirects aren't followed")
        : reasons[reason] || this.$pgettext("webhooks", "Delivery failed");

      return status ? `${text} (${status})` : text;
    },

    successText(item) {
      return item.last_success_at
        ? this.dateText(item.last_success_at)
        : this.$pgettext("webhooks", "None");
    },

    // deliveries fail until the URL is replaced
    undecryptable(item) {
      return item?.last_error?.reason === "invalid_encryption";
    },

    toggle() {
      this.checked = this.checked.size
        ? new Set()
        : new Set(this.filtered.map((item) => item.id));
    },

    toggleCheck(item) {
      const checked = new Set(this.checked);

      if (checked.has(item.id)) checked.delete(item.id);
      else checked.add(item.id);

      this.checked = checked;
    },

    provision(result) {
      this.put(result.webhook);
      this.secret = result.secret;
      this.secretDialog = true;
    },

    put(item) {
      const checked = new Set(this.checked);
      checked.delete(item.id);

      // changed webhooks keep their position, new ones are shown first
      this.items = this.items.some((entry) => entry.id === item.id)
        ? this.items.map((entry) => (entry.id === item.id ? item : entry))
        : [item, ...this.items];
      this.checked = checked;
    },

    validUrl(value) {
      const url = (value ?? "").trim();

      if (!/^https:\/\/\S+$/i.test(url)) return false;

      try {
        return !!new URL(url).hostname;
      } catch (_error) {
        return false;
      }
    },
  },

  watch: {
    statusFilter() {
      this.checked = new Set();
    },

    term() {
      this.checked = new Set();
    },
  },
};
</script>

<template>
  <v-container class="webhook-list">
    <v-sheet class="box scroll">
      <p class="text-medium-emphasis mb-4">
        {{
          $pgettext(
            "webhooks",
            "Send signed notifications when published content changes.",
          )
        }}
      </p>

      <v-alert
        v-if="serverText"
        type="warning"
        variant="tonal"
        class="webhook-server mb-4"
      >
        {{ serverText }}
      </v-alert>

      <div class="header">
        <div class="bulk">
          <v-checkbox-btn
            :model-value="checked.size > 0"
            @click.stop="toggle"
            :aria-label="$pgettext('webhooks', 'Toggle selection')"
          />

          <CmsActionMenu>
            <template #activator="{ props, label }">
              <v-btn
                v-bind="props"
                :disabled="!checked.size"
                :title="label"
                :icon="mdiDotsVertical"
                variant="text"
              />
            </template>
            <v-list-item>
              <v-btn
                :prepend-icon="mdiDelete"
                :disabled="saving"
                variant="text"
                @click="remove()"
                >{{ $pgettext("webhooks", "Delete") }} ({{
                  checked.size
                }})</v-btn
              >
            </v-list-item>
          </CmsActionMenu>

          <v-btn
            :title="$pgettext('webhooks', 'Add webhook')"
            :disabled="loading"
            :icon="mdiPlus"
            class="btn-add"
            color="primary"
            variant="tonal"
            @click="openAdd"
          />
        </div>

        <div class="search">
          <v-text-field
            v-model="term"
            :prepend-inner-icon="mdiMagnify"
            :label="$pgettext('webhooks', 'Search for')"
            variant="underlined"
            hide-details
            clearable
          />
          <v-select
            v-model="statusFilter"
            :items="statusItems"
            :label="$pgettext('webhooks', 'Status')"
            variant="underlined"
            hide-details
          />
        </div>

        <div class="layout">
          <v-btn
            :title="$pgettext('webhooks', 'Refresh')"
            :icon="mdiRefresh"
            :loading="loading"
            class="btn-reload"
            variant="text"
            @click="load"
          />
        </div>
      </div>

      <v-list class="items" role="list">
        <v-list-item
          v-for="item in filtered"
          :key="item.id"
          class="border-b rounded-0 pa-1"
          role="listitem"
        >
          <div class="d-flex align-center w-100">
            <div
              class="d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2"
            >
              <v-checkbox-btn
                :model-value="checked.has(item.id)"
                @update:model-value="toggleCheck(item)"
                :aria-label="$pgettext('webhooks', 'Toggle selection')"
              />

              <CmsActionMenu>
                <template #activator="{ props, label }">
                  <v-btn
                    v-bind="props"
                    :title="label"
                    :icon="mdiDotsVertical"
                    variant="text"
                  />
                </template>
                <v-list-item>
                  <v-btn
                    :prepend-icon="mdiPencil"
                    variant="text"
                    @click="openEdit(item)"
                    >{{ $pgettext("webhooks", "Edit") }}</v-btn
                  >
                </v-list-item>
                <v-list-item>
                  <v-btn
                    :prepend-icon="mdiLinkVariant"
                    variant="text"
                    @click="openReplace(item)"
                    >{{ $pgettext("webhooks", "Replace") }}</v-btn
                  >
                </v-list-item>
                <v-list-item>
                  <!-- a new secret doesn't help if the URL can't be decrypted -->
                  <v-btn
                    :prepend-icon="mdiKeyVariant"
                    :disabled="saving || undecryptable(item)"
                    class="btn-rotate"
                    variant="text"
                    @click="rotate(item)"
                    >{{ $pgettext("webhooks", "Rotate") }}</v-btn
                  >
                </v-list-item>
                <v-list-item>
                  <v-btn
                    :prepend-icon="mdiSend"
                    :disabled="saving"
                    class="btn-ping"
                    variant="text"
                    @click="ping(item)"
                    >{{ $pgettext("webhooks", "Send test event") }}</v-btn
                  >
                </v-list-item>
                <v-divider />
                <v-list-item>
                  <v-btn
                    :prepend-icon="mdiDelete"
                    :disabled="saving"
                    variant="text"
                    @click="remove(item)"
                    >{{ $pgettext("webhooks", "Delete") }}</v-btn
                  >
                </v-list-item>
              </CmsActionMenu>
            </div>

            <a href="#" class="item-content" @click.prevent="openEdit(item)">
              <div class="item-text">
                <div class="item-head">
                  <span class="item-title">{{
                    item.name || item.endpoint
                  }}</span>
                </div>
                <div v-if="item.name" class="item-subtitle item-endpoint">
                  {{ item.endpoint }}
                </div>
                <div class="item-subtitle">{{ item.events.join(", ") }}</div>
              </div>

              <div class="item-aux text-end">
                <div>
                  <v-chip
                    :color="item.status ? 'success' : undefined"
                    size="small"
                  >
                    {{
                      item.status
                        ? $pgettext("webhooks", "Active")
                        : $pgettext("webhooks", "Inactive")
                    }}
                  </v-chip>
                </div>
                <div class="item-subtitle">
                  {{ $pgettext("webhooks", "Last success") }}:
                  {{ successText(item) }}
                </div>
                <div class="item-subtitle">
                  {{ $pgettext("webhooks", "Last error") }}:
                  {{ errorText(item) }}
                </div>
                <div
                  v-if="item.paused_until"
                  class="item-subtitle webhook-paused text-warning"
                >
                  {{ $pgettext("webhooks", "Paused until") }}:
                  {{ dateText(item.paused_until) }}
                </div>
              </div>
            </a>
          </div>
        </v-list-item>
      </v-list>

      <p v-if="loading" class="loading">
        {{ $pgettext("webhooks", "Loading") }}
        <CmsLoadingSpinner width="32" height="32" />
      </p>
      <p v-else-if="!filtered.length" class="notfound">
        {{
          items.length
            ? $pgettext("webhooks", "No entries found")
            : $pgettext("webhooks", "No webhooks configured.")
        }}
      </p>

      <div class="btn-group">
        <v-btn
          :title="$pgettext('webhooks', 'Add webhook')"
          :disabled="loading"
          :icon="mdiPlus"
          class="btn-add"
          color="primary"
          variant="tonal"
          @click="openAdd"
        />
      </div>
    </v-sheet>
  </v-container>

  <CmsDialog
    v-model="dialog"
    :title="
      selected
        ? $pgettext('webhooks', 'Edit webhook')
        : $pgettext('webhooks', 'Add webhook')
    "
    :persistent="!!secret"
    max-width="640"
    @after-leave="secret = ''"
  >
    <template v-if="secret">
      <v-alert type="warning" variant="tonal" class="mb-4">
        {{
          $pgettext(
            "webhooks",
            "Copy this secret now. It will not be shown again.",
          )
        }}
      </v-alert>
      <v-text-field
        :model-value="secret"
        class="webhook-secret"
        variant="underlined"
        readonly
      />
    </template>
    <template v-else>
      <div class="webhook-status d-flex align-center ga-2">
        <!-- inactive subscriptions which can't be decrypted can't be activated -->
        <v-switch
          v-model="status"
          :aria-label="$pgettext('webhooks', 'Active')"
          :disabled="!!selected && !selected.status && undecryptable(selected)"
          color="success"
          density="compact"
          hide-details
          class="flex-grow-0 flex-shrink-0"
        />
        <span class="webhook-status-label text-no-wrap">{{
          $pgettext("webhooks", "Active")
        }}</span>
      </div>
      <v-text-field
        v-if="!selected"
        v-model="url"
        :label="$pgettext('webhooks', 'HTTPS endpoint URL')"
        :rules="[
          (value) =>
            validUrl(value) || $pgettext('webhooks', 'Not a valid URL'),
        ]"
        validate-on="invalid-input"
        variant="underlined"
        maxlength="500"
        autofocus
      />
      <template v-else>
        <p class="webhook-current text-medium-emphasis mt-4 mb-4">
          {{ $pgettext("webhooks", "Current destination") }}:
          {{ selected.endpoint }}
        </p>
        <v-alert
          v-if="undecryptable(selected)"
          type="warning"
          variant="tonal"
          class="webhook-undecryptable mb-4"
        >
          {{ reasonText("invalid_encryption") }}
        </v-alert>
      </template>
      <v-text-field
        v-model="name"
        :label="$pgettext('webhooks', 'Name')"
        class="webhook-name"
        variant="underlined"
        maxlength="100"
      />
      <v-select
        v-model="events"
        :items="names"
        :label="$pgettext('webhooks', 'Events')"
        variant="underlined"
        multiple
        chips
      />
    </template>

    <template #actions="{ close }">
      <template v-if="secret">
        <v-btn variant="outlined" @click="close">{{
          $pgettext("webhooks", "Done")
        }}</v-btn>
        <v-btn color="primary" variant="tonal" @click="copySecret" active>{{
          $pgettext("webhooks", "Copy secret")
        }}</v-btn>
      </template>
      <template v-else>
        <v-btn variant="outlined" @click="close">{{
          $pgettext("webhooks", "Cancel")
        }}</v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          :disabled="!events.length || (!selected && !validUrl(url))"
          :loading="saving"
          @click="save"
          active
          >{{ $pgettext("webhooks", "Save") }}</v-btn
        >
      </template>
    </template>
  </CmsDialog>

  <CmsDialog
    v-model="replaceDialog"
    :title="$pgettext('webhooks', 'Replace webhook destination')"
    max-width="640"
  >
    <p v-if="selected" class="webhook-current text-medium-emphasis mb-4">
      {{ $pgettext("webhooks", "Current destination") }}: {{ label(selected) }}
    </p>
    <v-text-field
      v-model="url"
      :label="$pgettext('webhooks', 'HTTPS endpoint URL')"
      :rules="[
        (value) => validUrl(value) || $pgettext('webhooks', 'Not a valid URL'),
      ]"
      validate-on="invalid-input"
      variant="underlined"
      maxlength="500"
      autofocus
    />
    <v-alert type="warning" variant="tonal">
      {{
        $pgettext(
          "webhooks",
          "Replacing the destination rotates the secret and disables the webhook.",
        )
      }}
    </v-alert>

    <template #actions="{ close }">
      <v-btn variant="outlined" @click="close">{{
        $pgettext("webhooks", "Cancel")
      }}</v-btn>
      <v-btn
        color="primary"
        variant="tonal"
        :disabled="!validUrl(url)"
        :loading="saving"
        @click="replace"
        active
        >{{ $pgettext("webhooks", "Replace") }}</v-btn
      >
    </template>
  </CmsDialog>

  <CmsDialog
    v-model="secretDialog"
    :title="$pgettext('webhooks', 'Webhook secret')"
    max-width="640"
    persistent
    @update:model-value="!$event && closeSecret()"
  >
    <v-alert type="warning" variant="tonal" class="mb-4">
      {{
        $pgettext(
          "webhooks",
          "Copy this secret now. It will not be shown again.",
        )
      }}
    </v-alert>
    <v-text-field :model-value="secret" variant="underlined" readonly />

    <template #actions>
      <v-btn variant="outlined" @click="closeSecret">{{
        $pgettext("webhooks", "Done")
      }}</v-btn>
      <v-btn color="primary" variant="tonal" @click="copySecret" active>{{
        $pgettext("webhooks", "Copy secret")
      }}</v-btn>
    </template>
  </CmsDialog>
</template>
