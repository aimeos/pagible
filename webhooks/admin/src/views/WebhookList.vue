<!-- @license MIT, https://opensource.org/license/mit -->

<script>
import gql from "graphql-tag";
import {
  mdiClose,
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
    actions: false,
    dialog: false,
    replaceDialog: false,
    secretDialog: false,
    loading: true,
    saving: false,
    items: [],
    checked: new Set(),
    menu: [],
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
      mdiClose,
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
        { title: this.$gettext("All"), value: null },
        { title: this.$gettext("Active"), value: true },
        { title: this.$gettext("Inactive"), value: false },
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
          this.put(data.saveWebhook);
        } else {
          const { data } = await this.apollo.mutate({
            mutation: ADD,
            variables: { input: { url: this.url.trim(), events: this.events } },
          });
          this.provision(data.addWebhook);
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
        this.replaceDialog = false;
        this.provision(data.replaceWebhook);
      }, this.$gettext("Error replacing webhook destination"));
    },

    async rotate(item) {
      await this.change(async () => {
        const { data } = await this.apollo.mutate({
          mutation: ROTATE,
          variables: { id: item.id },
        });
        this.provision(data.rotateWebhook);
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
    <div class="v-sheet box scroll">
      <p class="text-medium-emphasis mb-4">
        {{
          $gettext(
            "Send signed notifications when published content changes.",
          )
        }}
      </p>

      <div class="header">
        <div class="bulk">
          <v-checkbox-btn
            :model-value="checked.size > 0"
            @click.stop="toggle"
            :aria-label="$gettext('Toggle selection')"
          />

          <component
            :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
            v-model="actions"
            :aria-label="$gettext('Actions')"
            transition="scale-transition"
            location="end center"
            max-width="300"
          >
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                :disabled="!checked.size"
                :title="$gettext('Actions')"
                :icon="mdiDotsVertical"
                variant="text"
              />
            </template>
            <v-card>
              <v-card-title class="d-flex align-center">
                <span>{{ $gettext("Actions") }}</span>
                <v-spacer />
                <v-btn
                  :icon="mdiClose"
                  :aria-label="$gettext('Close')"
                  @click="actions = false"
                />
              </v-card-title>
              <div class="v-list" @click="actions = false">
                <div class="v-list-item">
                  <v-btn
                    :prepend-icon="mdiDelete"
                    :disabled="saving"
                    variant="text"
                    @click="remove()"
                  >{{ $gettext("Delete") }} ({{ checked.size }})</v-btn>
                </div>
              </div>
            </v-card>
          </component>

          <v-btn
            :title="$gettext('Add webhook')"
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
            :label="$gettext('Search for')"
            variant="underlined"
            hide-details
            clearable
          />
          <v-select
            v-model="statusFilter"
            :items="statusItems"
            :label="$gettext('Status')"
            variant="underlined"
            hide-details
          />
        </div>

        <div class="layout">
          <v-btn
            :title="$gettext('Refresh')"
            :icon="mdiRefresh"
            :loading="loading"
            class="btn-reload"
            variant="text"
            @click="load"
          />
        </div>
      </div>

      <div class="v-list items" role="list">
        <div
          v-for="(item, idx) in filtered"
          :key="item.id"
          class="v-list-item border-b rounded-0 pa-1"
          role="listitem"
        >
          <div class="d-flex align-center w-100">
            <div class="d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2">
              <v-checkbox-btn
                :model-value="checked.has(item.id)"
                @update:model-value="toggleCheck(item)"
                :aria-label="$gettext('Toggle selection')"
              />

              <component
                :is="$vuetify.display.xs ? 'v-dialog' : 'v-menu'"
                v-model="menu[idx]"
                :aria-label="$gettext('Actions')"
                transition="scale-transition"
                location="end center"
                max-width="300"
              >
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :title="$gettext('Actions')"
                    :icon="mdiDotsVertical"
                    variant="text"
                  />
                </template>
                <v-card>
                  <v-card-title class="d-flex align-center">
                    <span>{{ $gettext("Actions") }}</span>
                    <v-spacer />
                    <v-btn
                      :icon="mdiClose"
                      :aria-label="$gettext('Close')"
                      @click="menu[idx] = false"
                    />
                  </v-card-title>
                  <div class="v-list" @click="menu[idx] = false">
                    <div class="v-list-item">
                      <v-btn
                        :prepend-icon="mdiPencil"
                        variant="text"
                        @click="openEdit(item)"
                      >{{ $gettext("Edit") }}</v-btn>
                    </div>
                    <div class="v-list-item">
                      <v-btn
                        :prepend-icon="mdiLinkVariant"
                        variant="text"
                        @click="openReplace(item)"
                      >{{ $gettext("Replace") }}</v-btn>
                    </div>
                    <div class="v-list-item">
                      <v-btn
                        :prepend-icon="mdiKeyVariant"
                        variant="text"
                        @click="rotate(item)"
                      >{{ $gettext("Rotate") }}</v-btn>
                    </div>
                    <div class="border-t" />
                    <div class="v-list-item">
                      <v-btn
                        :prepend-icon="mdiDelete"
                        :disabled="saving"
                        variant="text"
                        @click="remove(item)"
                      >{{ $gettext("Delete") }}</v-btn>
                    </div>
                  </div>
                </v-card>
              </component>
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
                      item.status ? $gettext("Active") : $gettext("Inactive")
                    }}
                  </v-chip>
                </div>
                <div class="item-subtitle">
                  {{ $gettext("Last success") }}: {{ successText(item) }}
                </div>
                <div class="item-subtitle">
                  {{ $gettext("Failures") }}: {{ item.failures }} ·
                  {{ $gettext("Last error") }}: {{ errorText(item) }}
                </div>
              </div>
            </a>
          </div>
        </div>
      </div>

      <p v-if="loading" class="loading">
        {{ $gettext("Loading") }}
        <svg
          class="spinner"
          width="32"
          height="32"
          fill="currentColor"
          viewBox="0 0 24 24"
          xmlns="http://www.w3.org/2000/svg"
        >
          <circle class="spin1" cx="4" cy="12" r="3" />
          <circle class="spin1 spin2" cx="12" cy="12" r="3" />
          <circle class="spin1 spin3" cx="20" cy="12" r="3" />
        </svg>
      </p>
      <p v-else-if="!filtered.length" class="notfound">
        {{
          items.length
            ? $gettext("No entries found")
            : $gettext("No webhooks configured.")
        }}
      </p>

      <div class="btn-group">
        <v-btn
          :title="$gettext('Add webhook')"
          :disabled="loading"
          :icon="mdiPlus"
          class="btn-add"
          color="primary"
          variant="tonal"
          @click="openAdd"
        />
      </div>
    </div>
  </v-container>

  <v-dialog v-model="dialog" max-width="640">
    <v-card>
      <v-card-title class="d-flex align-center">
        <span>{{
          selected ? $gettext("Edit webhook") : $gettext("Add webhook")
        }}</span>
        <v-spacer />
        <v-btn
          :icon="mdiClose"
          :aria-label="$gettext('Close')"
          @click="dialog = false"
        />
      </v-card-title>
      <v-card-text>
        <v-text-field
          v-if="!selected"
          v-model="url"
          :label="$gettext('HTTPS endpoint URL')"
          variant="underlined"
          maxlength="500"
          autofocus
        />
        <v-select
          v-model="events"
          :items="names"
          :label="$gettext('Events')"
          variant="underlined"
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
        <v-btn variant="outlined" :loading="saving" @click="save">{{
          $gettext("Save")
        }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="replaceDialog" max-width="640">
    <v-card>
      <v-card-title class="d-flex align-center">
        <span>{{
          $gettext("Replace webhook destination")
        }}</span>
        <v-spacer />
        <v-btn
          :icon="mdiClose"
          :aria-label="$gettext('Close')"
          @click="replaceDialog = false"
        />
      </v-card-title>
      <v-card-text>
        <v-text-field
          v-model="url"
          :label="$gettext('HTTPS endpoint URL')"
          variant="underlined"
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
        <v-btn variant="outlined" :loading="saving" @click="replace">{{
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
        <v-text-field
          :model-value="secret"
          variant="underlined"
          readonly
        />
      </v-card-text>
      <v-card-actions>
        <v-btn variant="outlined" @click="copySecret">{{
          $gettext("Copy secret")
        }}</v-btn>
        <v-spacer />
        <v-btn
          variant="text"
          @click="
            secretDialog = false;
            secret = '';
          "
        >{{ $gettext("Done") }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
