<!-- @license MIT, https://opensource.org/license/mit -->

<script>
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
import WebhookDialog from "../components/WebhookDialog.vue";
import { ADD, LIST, PING, PURGE, ROTATE, SAVE } from "../graphql";
import { PURGE_BATCH, reasonText as formatReason } from "../webhooks";

export default {
  name: "WebhookList",

  components: { WebhookDialog },

  props: {
    // plugin panel definition passed by the admin
    panel: { type: Object, default: null },
  },

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
      failed: false,
      saving: false,
      testing: false,
      items: [],
      checked: new Set(),
      events: [],
      selected: null,
      term: "",
      filter: this.pluginAside?.(() => this.asideContent, defaults) ?? defaults,
      secret: "",
      server: { enabled: true, blocked: null },
      // counts local changes and list requests to ignore outdated responses
      changes: 0,
      loadSeq: 0,
      // the page was left, e.g. while a request was running
      left: false,
      // current time to hide pauses which are over
      now: Date.now(),
    };
  },

  setup() {
    return {
      mdiDeleteForever,
      mdiKeyVariant,
      mdiMagnify,
      mdiPencil,
      mdiRefresh,
      // shared props of the activator buttons of the action menus
      menuButton: { icon: mdiDotsVertical, variant: "text" },
      pausedColor:
        "color-mix(in srgb, rgb(var(--v-theme-warning)) 85%, rgb(var(--v-theme-on-surface)))",
    };
  },

  computed: {
    allChecked() {
      return (
        this.filtered.length > 0 &&
        this.filtered.every((item) => this.checked.has(item.id))
      );
    },

    // props of the "Add webhook" buttons above and below the list
    addButton() {
      return {
        title: this.$pgettext("webhooks", "Add webhook"),
        // webhooks can't be saved without the available events
        disabled: this.loading || !this.events.length,
        icon: mdiPlus,
        class: "btn-add",
        color: "primary",
        variant: "tonal",
      };
    },

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
          "All deliveries are blocked by the server configuration: %{reason}",
          { reason: this.reasonText(this.server.blocked) },
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
          open: true,
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
    this.timer = null;

    // the selection may contain webhooks which aren't shown anymore
    this.$watch(
      () => [this.filter.status, this.term],
      () => (this.checked = new Set()),
    );
  },

  mounted() {
    this.load();
    // only re-render when there are pauses which can end
    this.timer = setInterval(() => {
      if (this.items.some((item) => item.paused_until)) this.now = Date.now();
    }, 60000);
  },

  beforeUnmount() {
    clearInterval(this.timer);
    // responses of running requests are ignored after leaving the page
    this.loadSeq++;
    this.left = true;
  },

  methods: {
    async change(callback, failure) {
      if (this.saving) return;
      this.saving = true;

      try {
        await callback();
      } catch (error) {
        this.messages.error(failure, error);
      } finally {
        this.saving = false;
      }
    },

    dateText(value) {
      return new Date(value).toLocaleString(this.$vuetify.locale.current);
    },

    errorText(item) {
      const error = item.last_error;

      if (!error) return this.$pgettext("webhooks", "None");

      const text = this.reasonText(error.reason, error.status);
      return error.at
        ? this.$pgettext("webhooks", "%{reason} · %{date}", {
            reason: text,
            date: this.dateText(error.at),
          })
        : text;
    },

    async load() {
      const seq = ++this.loadSeq;
      const changes = this.changes;
      this.loading = true;

      try {
        const { data } = await this.apollo.query({
          query: LIST,
          fetchPolicy: "network-only",
        });

        if (seq !== this.loadSeq) return;

        // the list is outdated if webhooks were changed while it was loading
        if (changes !== this.changes) return this.load();

        this.items = data.cmsWebhooks;
        this.now = Date.now();
        this.checked = new Set();
        this.failed = false;

        // the open dialog shows the reloaded webhook
        if (this.selected) {
          this.selected =
            this.items.find((entry) => entry.id === this.selected.id) || this.selected;
        }
        this.events = data.cmsWebhookEvents;
        this.server = data.cmsWebhookServer;
      } catch (error) {
        if (seq === this.loadSeq) {
          this.failed = true;
          this.messages.error(this.$pgettext("webhooks", "Error fetching webhooks"), error);
        }
      } finally {
        if (seq === this.loadSeq) {
          this.loading = false;
        }
      }
    },

    openAdd() {
      this.selected = null;
      this.secret = "";
      this.dialog = true;
    },

    openEdit(item) {
      // a running rotate opens the dialog with the secret of its own webhook
      if (this.saving) return;
      this.selected = item;
      this.secret = "";
      this.dialog = true;
    },

    async ping(item) {
      // change() skips the test event while saving, which mustn't show the test as running
      if (this.saving) return;
      this.testing = true;

      try {
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
              this.changes++;
              this.messages.add(
                this.$pgettext("webhooks", "Test event delivered (%{status})", {
                  status: result.status,
                }),
                "success",
              );
            } else {
              this.messages.add(
                this.$pgettext("webhooks", "Test event failed: %{reason}", {
                  reason: this.reasonText(result.reason, result.status),
                }),
                "error",
              );
            }
          },
          this.$pgettext("webhooks", "Test event failed"),
        );
      } finally {
        this.testing = false;
      }
    },

    async purge(item = null) {
      const list = item
        ? [item]
        : this.items.filter((entry) => this.checked.has(entry.id));

      if (
        this.saving ||
        !list.length ||
        !(await this.confirm.purge(
          list.map((entry) => this.summary(entry)),
        ))
      ) {
        return;
      }

      const ids = list.map((entry) => entry.id);

      await this.change(
        async () => {
          for (let i = 0; i < ids.length; i += PURGE_BATCH) {
            const removed = new Set(ids.slice(i, i + PURGE_BATCH));

            await this.apollo.mutate({
              mutation: PURGE,
              variables: { id: [...removed] },
            });
            this.items = this.items.filter((entry) => !removed.has(entry.id));
            this.changes++;
            this.checked = new Set(
              [...this.checked].filter((id) => !removed.has(id)),
            );
          }
        },
        this.$pgettext("webhooks", "Error purging webhook"),
      );
    },

    put(item) {
      const checked = new Set(this.checked);
      checked.delete(item.id);

      // changed webhooks keep their position, new ones are shown first
      this.items = this.items.some((entry) => entry.id === item.id)
        ? this.items.map((entry) => (entry.id === item.id ? item : entry))
        : [item, ...this.items];
      this.checked = checked;
      this.changes++;
    },

    // deliveries are resumed by the server after the pause
    paused(item) {
      return !!item.paused_until && new Date(item.paused_until).getTime() > this.now;
    },

    reasonText(reason, status) {
      return formatReason(this.$pgettext, reason, status);
    },

    // shows the one-time secret, which is lost if the page was left before the server responded
    reveal(secret) {
      if (this.left) {
        this.messages.add(
          this.$pgettext(
            "webhooks",
            "The new webhook secret could not be shown after leaving the page, please rotate it to get a new one",
          ),
          "warning",
        );
        return;
      }

      this.secret = secret;
      this.dialog = true;
    },

    async rotate(item) {
      const question = this.$pgettext(
        "webhooks",
        "Rotate the secret of this webhook? Receivers must be updated with the new secret.",
      );

      if (
        this.saving ||
        !(await this.confirm.ask(this.$pgettext("webhooks", "Rotate"), question, [
          this.summary(item),
        ]))
      ) {
        return;
      }

      await this.change(
        async () => {
          const { data } = await this.apollo.mutate({
            mutation: ROTATE,
            variables: { id: item.id },
          });
          // shows the one-time secret in the webhook dialog
          this.put(data.rotateWebhook.webhook);
          this.selected = data.rotateWebhook.webhook;
          this.reveal(data.rotateWebhook.secret);
        },
        this.$pgettext("webhooks", "Error rotating webhook secret"),
      );
    },

    async save(form) {
      const input = {
        name: form.name.trim(),
        events: form.events,
        status: form.status,
      };

      await this.change(
        async () => {
          if (this.selected) {
            const { data } = await this.apollo.mutate({
              mutation: SAVE,
              variables: { id: this.selected.id, input },
            });
            this.put(data.saveWebhook);
            this.dialog = false;
          } else {
            const { data } = await this.apollo.mutate({
              mutation: ADD,
              variables: { input: { ...input, url: form.url.trim() } },
            });
            // keep (or reopen) the dialog to show the one-time secret
            this.put(data.addWebhook.webhook);
            this.reveal(data.addWebhook.secret);
          }
        },
        this.$pgettext("webhooks", "Error saving webhook"),
      );
    },

    successText(item) {
      return item.last_success_at
        ? this.dateText(item.last_success_at)
        : this.$pgettext("webhooks", "None");
    },

    // endpoints lack the last path segment, so the name tells webhooks apart
    summary(item) {
      return { name: item.name || item.endpoint, info: item.name ? item.endpoint : "" };
    },

    // checks all webhooks if one is unchecked, otherwise unchecks all
    toggle() {
      this.checked = this.allChecked
        ? new Set()
        : new Set(this.filtered.map((item) => item.id));
    },

    toggleCheck(item) {
      const checked = new Set(this.checked);
      if (!checked.delete(item.id)) checked.add(item.id);
      this.checked = checked;
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
            :model-value="allChecked"
            :indeterminate="checked.size > 0 && !allChecked"
            @click.stop="toggle"
            :aria-label="$pgettext('webhooks', 'Toggle selection')"
          />

          <CmsActionMenu>
            <template #activator="{ props, label }">
              <v-btn
                v-bind="{ ...props, ...menuButton }"
                :disabled="!checked.size"
                :title="label"
              />
            </template>
            <v-list-item>
              <v-btn
                :prepend-icon="mdiDeleteForever"
                :disabled="saving"
                variant="text"
                @click="purge()"
                >{{
                  $pgettext("webhooks", "Purge (%{count})", {
                    count: checked.size,
                  })
                }}</v-btn
              >
            </v-list-item>
          </CmsActionMenu>

          <v-btn v-bind="addButton" @click="openAdd" />
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
                    v-bind="{ ...props, ...menuButton }"
                    :title="label"
                  />
                </template>
                <v-list-item>
                  <v-btn
                    :prepend-icon="mdiPencil"
                    :disabled="saving"
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

            <a
              href="#"
              class="item-content"
              :aria-disabled="saving"
              :style="saving ? { opacity: 0.6, cursor: 'default' } : null"
              @click.prevent="openEdit(item)"
            >
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
                  {{
                    $pgettext("webhooks", "Last success: %{date}", {
                      date: successText(item),
                    })
                  }}
                </div>
                <div class="item-subtitle">
                  {{
                    $pgettext("webhooks", "Last error: %{reason}", {
                      reason: errorText(item),
                    })
                  }}
                </div>
                <!-- blended with the text color to keep AA contrast on hovered and focused list items -->
                <div
                  v-if="paused(item)"
                  class="item-subtitle webhook-paused"
                  :style="{ color: pausedColor }"
                >
                  {{
                    $pgettext("webhooks", "Paused until: %{date}", {
                      date: dateText(item.paused_until),
                    })
                  }}
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
      <p v-else-if="failed && !items.length" class="notfound load-error">
        {{ $pgettext("webhooks", "Error fetching webhooks") }}
        <v-btn class="btn-retry" variant="text" @click="load">{{
          $pgettext("webhooks", "Refresh")
        }}</v-btn>
      </p>
      <p v-else-if="!filtered.length" class="notfound">
        {{
          items.length
            ? $pgettext("webhooks", "No entries found")
            : $pgettext("webhooks", "No webhooks configured.")
        }}
      </p>

      <div class="btn-group">
        <v-btn v-bind="addButton" @click="openAdd" />
      </div>
    </v-sheet>
  </v-container>

  <WebhookDialog
    v-model="dialog"
    :webhook="selected"
    :events="events"
    :secret="secret"
    :saving="saving"
    :testing="testing"
    :testable="server.enabled && server.blocked !== 'invalid_policy'"
    @closed="secret = ''"
    @save="save($event)"
    @test="ping($event)"
  />
</template>

