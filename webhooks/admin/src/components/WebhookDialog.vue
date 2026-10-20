<!-- @license MIT, https://opensource.org/license/mit -->

<script>
import { blank, reasonText, undecryptable, validUrl } from "../webhooks";

/**
 * Form for adding and editing a webhook which shows the one-time secret after adding or rotating it.
 * The parent saves the form and tests the webhook.
 */
export default {
  name: "WebhookDialog",

  inject: {
    messages: {},
  },

  props: {
    modelValue: { type: Boolean, default: false },
    // webhook to edit, a new one is added if NULL
    webhook: { type: Object, default: null },
    // names of the available events
    events: { type: Array, default: () => [] },
    // one-time secret shown instead of the form
    secret: { type: String, default: "" },
    saving: { type: Boolean, default: false },
    testing: { type: Boolean, default: false },
    // test events can be sent with the current server configuration
    testable: { type: Boolean, default: true },
  },

  emits: ["update:modelValue", "closed", "save", "test"],

  data() {
    return {
      form: blank(),
    };
  },

  setup() {
    return { reasonText, undecryptable, validUrl };
  },

  computed: {
    // the form can be saved if events are selected and new webhooks have a valid URL
    valid() {
      return !!this.form.events.length && (!!this.webhook || validUrl(this.form.url));
    },
  },

  methods: {
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

    reset() {
      const item = this.webhook;
      this.form = item
        ? { url: "", name: item.name, events: [...item.events], status: item.status }
        : blank();
    },

    // Enter confirming a word of an input method editor doesn't save the form,
    // Safari sends it after the composition ended with the key code only
    submit(ev) {
      if (ev.isComposing || ev.keyCode === 229) return;

      ev.preventDefault();
      this.save();
    },

    save() {
      if (!this.valid || this.saving || this.testing) return;

      this.$emit("save", this.form);
    },
  },

  watch: {
    // the form is filled each time the dialog opens
    modelValue: {
      immediate: true,
      handler(open) {
        open && this.reset();
      },
    },
  },
};
</script>

<template>
  <CmsDialog
    :model-value="modelValue"
    :title="
      secret
        ? $pgettext('webhooks', 'Webhook secret')
        : webhook
          ? $pgettext('webhooks', 'Edit webhook')
          : $pgettext('webhooks', 'Add webhook')
    "
    :persistent="!!secret || saving"
    max-width="640"
    @update:model-value="$emit('update:modelValue', $event)"
    @after-leave="$emit('closed')"
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
        :aria-label="$pgettext('webhooks', 'Webhook secret')"
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
          v-model="form.status"
          :aria-label="$pgettext('webhooks', 'Active')"
          :hint="$pgettext('webhooks', 'Inactive webhooks receive no events and their queued deliveries are dropped')"
          color="primary"
          hide-details="auto"
          inset
        />
      </div>
      <v-text-field
        v-if="!webhook"
        v-model="form.url"
        :label="$pgettext('webhooks', 'HTTPS endpoint URL')"
        :hint="$pgettext('webhooks', 'Address receiving the signed events, it cannot be changed later')"
        :rules="[
          (value) =>
            validUrl(value) || $pgettext('webhooks', 'Not a valid URL'),
        ]"
        validate-on="invalid-input"
        variant="underlined"
        maxlength="500"
        autofocus
        @keydown.enter="submit"
      />
      <template v-else>
        <p class="webhook-current on-surface text-break mt-4 mb-4">{{ webhook.endpoint }}</p>
        <v-alert
          v-if="undecryptable(webhook)"
          type="warning"
          variant="tonal"
          class="webhook-undecryptable mb-4"
        >
          {{ reasonText($pgettext, "invalid_encryption") }}
        </v-alert>
      </template>
      <v-select
        v-model="form.events"
        :items="events"
        :label="$pgettext('webhooks', 'Events')"
        :hint="$pgettext('webhooks', 'Events sent to the webhook, queued deliveries of removed events are cancelled')"
        variant="underlined"
        multiple
        chips
      />
      <v-text-field
        v-model="form.name"
        :label="$pgettext('webhooks', 'Name')"
        :hint="$pgettext('webhooks', 'Optional name to tell webhooks with the same endpoint apart, visible to all webhook editors')"
        class="webhook-name"
        variant="underlined"
        maxlength="100"
        @keydown.enter="submit"
      />
    </template>

    <!-- sends a test event to the saved destination -->
    <template v-if="!secret && webhook" #actions-start>
      <v-btn
        class="btn-test"
        variant="outlined"
        :disabled="saving || !testable"
        :loading="testing"
        @click="$emit('test', webhook)"
        active
        >{{ $pgettext("webhooks", "Test") }}</v-btn
      >
    </template>

    <template #actions="{ close }">
      <template v-if="secret">
        <v-btn variant="text" @click="close">{{
          $pgettext("webhooks", "Done")
        }}</v-btn>
        <v-btn color="primary" variant="tonal" @click="copySecret">{{
          $pgettext("webhooks", "Copy secret")
        }}</v-btn>
      </template>
      <template v-else>
        <v-btn variant="text" :disabled="saving" @click="close">{{
          $pgettext("webhooks", "Cancel")
        }}</v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          :disabled="testing || !valid"
          :loading="saving && !testing"
          @click="save()"
          >{{ $pgettext("webhooks", "Save") }}</v-btn
        >
      </template>
    </template>
  </CmsDialog>
</template>
