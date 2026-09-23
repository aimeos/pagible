<!-- @license MIT, https://opensource.org/license/mit -->

<script>
import gql from "graphql-tag";
import {
  mdiCheckCircleOutline,
  mdiCloseCircleOutline,
  mdiDeleteForever,
  mdiDotsVertical,
  mdiKeyVariant,
  mdiMagnify,
  mdiPencil,
  mdiPlaylistCheck,
  mdiPlus,
  mdiRefresh,
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

const PURGE = gql`
  mutation PurgeWebhook($id: [ID!]!) {
    purgeWebhook(id: $id)
  }
`;

export default {
  name: "WebhookList",

  inject: {
    apollo: {},
    confirm: {},
    messages: {},
    // filter sidebar of the admin, not available outside of its plugin panel
    pluginAside: { default: null },
  },

  data() {
    const defaults = { status: null };

    return {
      dialog: false,
      loading: true,
      saving: false,
      testing: false,
      items: [],
      checked: new Set(),
      names: [],
      selected: null,
      term: "",
      filter: this.pluginAside?.(() => this.asideContent, defaults) ?? defaults,
      url: "",
      name: "",
      events: [],
      status: false,
      secret: "",
      server: { enabled: true, blocked: null },
    };
  },

  setup() {
    return {
      mdiDeleteForever,
      mdiDotsVertical,
      mdiKeyVariant,
      mdiMagnify,
      mdiPencil,
      mdiPlus,
      mdiRefresh,
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

      return "";
    },

    filtered() {
      const term = (this.term ?? "").trim().toLocaleLowerCase();

      return this.items.filter((item) => {
        if (this.filter.status !== null && item.status !== this.filter.status)
          return false;

        return (
          !term ||
          item.name.toLocaleLowerCase().includes(term) ||
          item.endpoint.toLocaleLowerCase().includes(term) ||
          item.events.some((event) => event.toLocaleLowerCase().includes(term))
        );
      });
    },

    asideContent() {
      return [
        {
          key: "status",
          title: this.$pgettext("webhooks", "Status"),
          items: [
            {
              title: this.$pgettext("webhooks", "All"),
              icon: mdiPlaylistCheck,
              value: { status: null },
            },
            {
              title: this.$pgettext("webhooks", "Active"),
              icon: mdiCheckCircleOutline,
              value: { status: true },
            },
            {
              title: this.$pgettext("webhooks", "Inactive"),
              icon: mdiCloseCircleOutline,
              value: { status: false },
            },
          ],
        },
      ];
    },
  },

  created() {
    // the selection may contain webhooks which aren't shown anymore
    this.$watch(
      () => [this.filter.status, this.term],
      () => (this.checked = new Set()),
    );
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
        this.server = data.cmsWebhookServer;
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
          // shows the one-time secret in the webhook dialog
          this.put(data.rotateWebhook.webhook);
          this.secret = data.rotateWebhook.secret;
          this.dialog = true;
        },
        this.$pgettext("webhooks", "Error rotating webhook secret"),
      );
    },

    async ping(item) {
      this.testing = true;
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
      this.testing = false;
    },

    async purge(item = null) {
      const list = item
        ? [item]
        : this.items.filter((entry) => this.checked.has(entry.id));

      if (
        this.saving ||
        !list.length ||
        !(await this.confirm.purge(
          list.map((entry) => ({
            name: entry.name || entry.endpoint,
            info: entry.name ? entry.endpoint : "",
          })),
        ))
      ) {
        return;
      }

      const ids = list.map((entry) => entry.id);

      await this.change(
        async () => {
          // the server purges up to 100 webhooks at once, see purgeWebhook in the GraphQL schema
          for (let i = 0; i < ids.length; i += 100) {
            const removed = new Set(ids.slice(i, i + 100));

            await this.apollo.mutate({
              mutation: PURGE,
              variables: { id: [...removed] },
            });
            this.items = this.items.filter((entry) => !removed.has(entry.id));
            this.checked = new Set(
              [...this.checked].filter((id) => !removed.has(id)),
            );
          }
        },
        this.$pgettext("webhooks", "Error purging webhook"),
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

    // endpoints lack the last path segment, so the name tells webhooks apart
    label(item) {
      return item.name ? `${item.name} · ${item.endpoint}` : item.endpoint;
    },

    reasonText(reason, status) {
      const reasons = {
        connection_failed: this.$pgettext("webhooks", "Connection failed"),
        destination_not_allowed: this.$pgettext("webhooks", "Access denied"),
        invalid_encryption: this.$pgettext("webhooks", "Secret can't be decrypted, rotate it"),
        invalid_policy: this.$pgettext("webhooks", "Blocked by server configuration"),
        invalid_url: this.$pgettext("webhooks", "Not a valid URL"),
        queue_failed: this.$pgettext("webhooks", "Queue unavailable"),
        resolution_failed: this.$pgettext("webhooks", "Host not found"),
        response_headers_too_large: this.$pgettext("webhooks", "Response too large"),
        timeout: this.$pgettext("webhooks", "Request timed out"),
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

    // deliveries fail until the secret is rotated
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
                :prepend-icon="mdiDeleteForever"
                :disabled="saving"
                variant="text"
                @click="purge()"
                >{{ $pgettext("webhooks", "Purge") }} ({{
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
                    :prepend-icon="mdiKeyVariant"
                    :disabled="saving"
                    class="btn-rotate"
                    variant="text"
                    @click="rotate(item)"
                    >{{ $pgettext("webhooks", "Rotate") }}</v-btn
                  >
                </v-list-item>
                <v-divider />
                <v-list-item>
                  <v-btn
                    :prepend-icon="mdiDeleteForever"
                    :disabled="saving"
                    variant="text"
                    @click="purge(item)"
                    >{{ $pgettext("webhooks", "Purge") }}</v-btn
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
      secret
        ? $pgettext('webhooks', 'Webhook secret')
        : selected
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
      <div class="webhook-status">
        <div class="webhook-status-label label d-flex align-center font-weight-bold mb-1">
          {{ $pgettext("webhooks", "Active") }}
        </div>
        <v-switch
          v-model="status"
          :aria-label="$pgettext('webhooks', 'Active')"
          color="primary"
          hide-details
          inset
        />
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
        <p class="webhook-current on-surface text-break mt-4 mb-4">{{ selected.endpoint }}</p>
        <v-alert
          v-if="undecryptable(selected)"
          type="warning"
          variant="tonal"
          class="webhook-undecryptable mb-4"
        >
          {{ reasonText("invalid_encryption") }}
        </v-alert>
      </template>
      <v-select
        v-model="events"
        :items="names"
        :label="$pgettext('webhooks', 'Events')"
        variant="underlined"
        multiple
        chips
      />
      <v-text-field
        v-model="name"
        :label="$pgettext('webhooks', 'Name')"
        class="webhook-name"
        variant="underlined"
        maxlength="100"
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
        <!-- sends a test event to the saved destination, placed before the spacer of the dialog -->
        <v-btn
          v-if="selected"
          class="btn-test order-first"
          color="warning"
          variant="tonal"
          :disabled="saving || !server.enabled"
          :loading="testing"
          @click="ping(selected)"
          active
          >{{ $pgettext("webhooks", "Test") }}</v-btn
        >
        <v-btn variant="outlined" @click="close">{{
          $pgettext("webhooks", "Cancel")
        }}</v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          :disabled="
            testing || !events.length || (!selected && !validUrl(url))
          "
          :loading="saving && !testing"
          @click="save"
          active
          >{{ $pgettext("webhooks", "Save") }}</v-btn
        >
      </template>
    </template>
  </CmsDialog>
</template>
