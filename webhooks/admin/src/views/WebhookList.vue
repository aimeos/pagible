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
} from "@mdi/js";

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
    term: "",
    statusFilter: null,
    url: "",
    events: [],
    status: false,
    secret: "",
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
    };
  },

  computed: {
    filtered() {
      const term = (this.term ?? "").trim().toLocaleLowerCase();

      return this.items.filter((item) => {
        if (this.statusFilter !== null && item.status !== this.statusFilter)
          return false;

        return (
          !term ||
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
      if (!this.events.length || (!this.selected && !this.url.trim())) return;

      await this.change(
        async () => {
          if (this.selected) {
            const { data } = await this.apollo.mutate({
              mutation: SAVE,
              variables: {
                id: this.selected.id,
                input: { events: this.events, status: this.status },
              },
            });
            this.put(data.saveWebhook);
          } else {
            const { data } = await this.apollo.mutate({
              mutation: ADD,
              variables: {
                input: { url: this.url.trim(), events: this.events },
              },
            });
            this.provision(data.addWebhook);
          }
          this.dialog = false;
        },
        this.$pgettext("webhooks", "Error saving webhook"),
      );
    },

    async replace() {
      if (!this.selected || !this.url.trim()) return;

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

    async remove(item = null) {
      const ids = item ? [item.id] : [...this.checked];
      const question = item
        ? this.$pgettext("webhooks", "Delete this webhook?")
        : `${this.$pgettext("webhooks", "Delete")} (${ids.length})?`;

      if (this.saving || !ids.length || !window.confirm(question)) return;

      await this.change(
        async () => {
          await this.apollo.mutate({
            mutation: DROP,
            variables: { id: ids },
          });
          const removed = new Set(ids);
          this.items = this.items.filter((entry) => !removed.has(entry.id));
          this.checked = new Set(
            [...this.checked].filter((id) => !removed.has(id)),
          );
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
      if (!item.last_error) return this.$pgettext("webhooks", "None");
      const status = item.last_error.status
        ? ` (${item.last_error.status})`
        : "";
      const reasons = {
        destination_not_allowed: this.$pgettext("webhooks", "Access denied"),
        invalid_header: this.$pgettext("webhooks", "Value has invalid format"),
        invalid_url: this.$pgettext("webhooks", "Not a valid URL"),
      };
      return `${reasons[item.last_error.reason] || this.$pgettext("webhooks", "Delivery failed")}${status}`;
    },

    successText(item) {
      return item.last_success_at
        ? new Date(item.last_success_at).toLocaleString(
            this.$vuetify.locale.current,
          )
        : this.$pgettext("webhooks", "None");
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

      this.items = [
        item,
        ...this.items.filter((entry) => entry.id !== item.id),
      ];
      this.checked = checked;
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
                  <v-btn
                    :prepend-icon="mdiKeyVariant"
                    variant="text"
                    @click="rotate(item)"
                    >{{ $pgettext("webhooks", "Rotate") }}</v-btn
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
                  <span class="item-title">{{ item.endpoint }}</span>
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
                  {{ $pgettext("webhooks", "Failures") }}: {{ item.failures }} ·
                  {{ $pgettext("webhooks", "Last error") }}:
                  {{ errorText(item) }}
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
    max-width="640"
  >
    <div v-if="selected" class="webhook-status d-flex align-center ga-2">
      <v-switch
        v-model="status"
        :aria-label="$pgettext('webhooks', 'Active')"
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
      variant="underlined"
      maxlength="500"
      autofocus
    />
    <v-select
      v-model="events"
      :items="names"
      :label="$pgettext('webhooks', 'Events')"
      variant="underlined"
      multiple
      chips
    />
    <v-alert v-if="!selected" type="info" variant="tonal">
      {{
        $pgettext(
          "webhooks",
          "New webhooks are inactive until you save them as active.",
        )
      }}
    </v-alert>

    <template #actions="{ close }">
      <v-btn variant="text" @click="close">{{
        $pgettext("webhooks", "Cancel")
      }}</v-btn>
      <v-btn
        color="primary"
        variant="flat"
        :disabled="!events.length || (!selected && !url.trim())"
        :loading="saving"
        @click="save"
        >{{ $pgettext("webhooks", "Save") }}</v-btn
      >
    </template>
  </CmsDialog>

  <CmsDialog
    v-model="replaceDialog"
    :title="$pgettext('webhooks', 'Replace webhook destination')"
    max-width="640"
  >
    <v-text-field
      v-model="url"
      :label="$pgettext('webhooks', 'HTTPS endpoint URL')"
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
      <v-btn variant="text" @click="close">{{
        $pgettext("webhooks", "Cancel")
      }}</v-btn>
      <v-btn
        color="primary"
        variant="flat"
        :disabled="!url.trim()"
        :loading="saving"
        @click="replace"
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
      <v-btn variant="text" @click="closeSecret">{{
        $pgettext("webhooks", "Done")
      }}</v-btn>
      <v-btn color="primary" variant="flat" @click="copySecret">{{
        $pgettext("webhooks", "Copy secret")
      }}</v-btn>
    </template>
  </CmsDialog>
</template>
