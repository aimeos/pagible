import { Fragment as e, createBlock as t, createCommentVNode as n, createElementBlock as r, createElementVNode as i, createSlots as a, createTextVNode as o, createVNode as s, mergeProps as c, normalizeStyle as l, openBlock as u, renderList as d, resolveComponent as f, toDisplayString as p, withCtx as m, withKeys as h, withModifiers as g } from "vue";
import _ from "graphql-tag";
// ../../node_modules/@mdi/js/mdi.js
var v = "M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20M16.59 7.58L10 14.17L7.41 11.59L6 13L10 17L18 9L16.59 7.58Z", y = "M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22C17.53,22 22,17.53 22,12C22,6.47 17.53,2 12,2M14.59,8L12,10.59L9.41,8L8,9.41L10.59,12L8,14.59L9.41,16L12,13.41L14.59,16L16,14.59L13.41,12L16,9.41L14.59,8Z", b = "M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M8.46,11.88L9.87,10.47L12,12.59L14.12,10.47L15.53,11.88L13.41,14L15.53,16.12L14.12,17.53L12,15.41L9.88,17.53L8.47,16.12L10.59,14L8.46,11.88M15.5,4L14.5,3H9.5L8.5,4H5V6H19V4H15.5Z", x = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", S = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", C = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", w = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", T = "M14 10H3V12H14V10M14 6H3V8H14V6M3 16H10V14H3V16M21.5 11.5L23 13L16 20L11.5 15.5L13 14L16 17L21.5 11.5Z", E = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", D = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", O = () => ({
	url: "",
	name: "",
	events: [],
	status: !1
});
function k(e, t, n = null) {
	let r = {
		connection_failed: e("webhooks", "Connection failed"),
		destination_not_allowed: e("webhooks", "Access denied"),
		invalid_encryption: e("webhooks", "Secret can't be decrypted, rotate it"),
		invalid_policy: e("webhooks", "Invalid list of denied networks"),
		invalid_queue: e("webhooks", "Queue unavailable"),
		invalid_url: e("webhooks", "Not a valid URL"),
		queue_failed: e("webhooks", "Queue unavailable"),
		resolution_failed: e("webhooks", "Host not found"),
		response_headers_too_large: e("webhooks", "Response too large"),
		timeout: e("webhooks", "Request timed out")
	}, i = t === "http_error" && n >= 300 && n < 400 ? e("webhooks", "Redirects aren't followed") : r[t] || e("webhooks", "Delivery failed");
	return n ? e("webhooks", "%{reason} (%{status})", {
		reason: i,
		status: n
	}) : i;
}
function A(e) {
	return e?.last_error?.reason === "invalid_encryption";
}
function j(e) {
	let t = (e ?? "").trim();
	if (!/^https:\/\/\S+$/i.test(t)) return !1;
	try {
		return !!new URL(t).hostname;
	} catch {
		return !1;
	}
}
// \0plugin-vue:export-helper
var M = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, N = {
	name: "WebhookDialog",
	inject: { messages: {} },
	props: {
		modelValue: {
			type: Boolean,
			default: !1
		},
		webhook: {
			type: Object,
			default: null
		},
		events: {
			type: Array,
			default: () => []
		},
		secret: {
			type: String,
			default: ""
		},
		saving: {
			type: Boolean,
			default: !1
		},
		testing: {
			type: Boolean,
			default: !1
		},
		testable: {
			type: Boolean,
			default: !0
		}
	},
	emits: [
		"update:modelValue",
		"closed",
		"save",
		"test"
	],
	data() {
		return { form: O() };
	},
	setup() {
		return {
			reasonText: k,
			undecryptable: A,
			validUrl: j
		};
	},
	computed: { valid() {
		return !!this.form.events.length && (!!this.webhook || j(this.form.url));
	} },
	methods: {
		async copySecret() {
			try {
				await navigator.clipboard.writeText(this.secret), this.messages.add(this.$pgettext("webhooks", "Secret copied"), "success");
			} catch {
				this.messages.add(this.$pgettext("webhooks", "Unable to copy secret"), "error");
			}
		},
		reset() {
			let e = this.webhook;
			this.form = e ? {
				url: "",
				name: e.name,
				events: [...e.events],
				status: e.status
			} : O();
		},
		submit(e) {
			e.isComposing || e.keyCode === 229 || (e.preventDefault(), this.save());
		},
		save() {
			!this.valid || this.saving || this.testing || this.$emit("save", this.form);
		}
	},
	watch: { modelValue: {
		immediate: !0,
		handler(e) {
			e && this.reset();
		}
	} }
}, P = { class: "webhook-status" }, ee = { class: "webhook-status-label label d-flex align-center font-weight-bold mb-1" }, F = { class: "webhook-current on-surface text-break mt-4 mb-4" };
function I(c, l, d, g, _, v) {
	let y = f("v-alert"), b = f("v-text-field"), x = f("v-switch"), S = f("v-select"), C = f("v-btn"), w = f("CmsDialog");
	return u(), t(w, {
		"model-value": d.modelValue,
		title: d.secret ? c.$pgettext("webhooks", "Webhook secret") : d.webhook ? c.$pgettext("webhooks", "Edit webhook") : c.$pgettext("webhooks", "Add webhook"),
		persistent: !!d.secret || d.saving,
		"max-width": "640",
		"onUpdate:modelValue": l[6] ||= (e) => c.$emit("update:modelValue", e),
		onAfterLeave: l[7] ||= (e) => c.$emit("closed")
	}, a({
		actions: m(({ close: t }) => [d.secret ? (u(), r(e, { key: 0 }, [s(C, {
			variant: "text",
			onClick: t
		}, {
			default: m(() => [o(p(c.$pgettext("webhooks", "Done")), 1)]),
			_: 1
		}, 8, ["onClick"]), s(C, {
			color: "primary",
			variant: "tonal",
			onClick: v.copySecret
		}, {
			default: m(() => [o(p(c.$pgettext("webhooks", "Copy secret")), 1)]),
			_: 1
		}, 8, ["onClick"])], 64)) : (u(), r(e, { key: 1 }, [s(C, {
			variant: "text",
			disabled: d.saving,
			onClick: t
		}, {
			default: m(() => [o(p(c.$pgettext("webhooks", "Cancel")), 1)]),
			_: 1
		}, 8, ["disabled", "onClick"]), s(C, {
			color: "primary",
			variant: "tonal",
			disabled: d.testing || !v.valid,
			loading: d.saving && !d.testing,
			onClick: l[5] ||= (e) => v.save()
		}, {
			default: m(() => [o(p(c.$pgettext("webhooks", "Save")), 1)]),
			_: 1
		}, 8, ["disabled", "loading"])], 64))]),
		default: m(() => [d.secret ? (u(), r(e, { key: 0 }, [s(y, {
			type: "warning",
			variant: "tonal",
			class: "mb-4"
		}, {
			default: m(() => [o(p(c.$pgettext("webhooks", "Copy this secret now. It will not be shown again.")), 1)]),
			_: 1
		}), s(b, {
			"model-value": d.secret,
			"aria-label": c.$pgettext("webhooks", "Webhook secret"),
			class: "webhook-secret",
			variant: "underlined",
			readonly: ""
		}, null, 8, ["model-value", "aria-label"])], 64)) : (u(), r(e, { key: 1 }, [
			i("div", P, [i("div", ee, p(c.$pgettext("webhooks", "Active")), 1), s(x, {
				modelValue: _.form.status,
				"onUpdate:modelValue": l[0] ||= (e) => _.form.status = e,
				"aria-label": c.$pgettext("webhooks", "Active"),
				hint: c.$pgettext("webhooks", "Inactive webhooks receive no events and their queued deliveries are dropped"),
				color: "primary",
				"hide-details": "auto",
				inset: ""
			}, null, 8, [
				"modelValue",
				"aria-label",
				"hint"
			])]),
			d.webhook ? (u(), r(e, { key: 1 }, [i("p", F, p(d.webhook.endpoint), 1), g.undecryptable(d.webhook) ? (u(), t(y, {
				key: 0,
				type: "warning",
				variant: "tonal",
				class: "webhook-undecryptable mb-4"
			}, {
				default: m(() => [o(p(g.reasonText(c.$pgettext, "invalid_encryption")), 1)]),
				_: 1
			})) : n("", !0)], 64)) : (u(), t(b, {
				key: 0,
				modelValue: _.form.url,
				"onUpdate:modelValue": l[1] ||= (e) => _.form.url = e,
				label: c.$pgettext("webhooks", "HTTPS endpoint URL"),
				hint: c.$pgettext("webhooks", "Address receiving the signed events, it cannot be changed later"),
				rules: [(e) => g.validUrl(e) || c.$pgettext("webhooks", "Not a valid URL")],
				"validate-on": "invalid-input",
				variant: "underlined",
				maxlength: "500",
				autofocus: "",
				onKeydown: h(v.submit, ["enter"])
			}, null, 8, [
				"modelValue",
				"label",
				"hint",
				"rules",
				"onKeydown"
			])),
			s(S, {
				modelValue: _.form.events,
				"onUpdate:modelValue": l[2] ||= (e) => _.form.events = e,
				items: d.events,
				label: c.$pgettext("webhooks", "Events"),
				hint: c.$pgettext("webhooks", "Events sent to the webhook, queued deliveries of removed events are cancelled"),
				variant: "underlined",
				multiple: "",
				chips: ""
			}, null, 8, [
				"modelValue",
				"items",
				"label",
				"hint"
			]),
			s(b, {
				modelValue: _.form.name,
				"onUpdate:modelValue": l[3] ||= (e) => _.form.name = e,
				label: c.$pgettext("webhooks", "Name"),
				hint: c.$pgettext("webhooks", "Optional name to tell webhooks with the same endpoint apart, visible to all webhook editors"),
				class: "webhook-name",
				variant: "underlined",
				maxlength: "100",
				onKeydown: h(v.submit, ["enter"])
			}, null, 8, [
				"modelValue",
				"label",
				"hint",
				"onKeydown"
			])
		], 64))]),
		_: 2
	}, [!d.secret && d.webhook ? {
		name: "actions-start",
		fn: m(() => [s(C, {
			class: "btn-test",
			variant: "outlined",
			disabled: d.saving || !d.testable,
			loading: d.testing,
			onClick: l[4] ||= (e) => c.$emit("test", d.webhook),
			active: ""
		}, {
			default: m(() => [o(p(c.$pgettext("webhooks", "Test")), 1)]),
			_: 1
		}, 8, ["disabled", "loading"])]),
		key: "0"
	} : void 0]), 1032, [
		"model-value",
		"title",
		"persistent"
	]);
}
var L = /*#__PURE__*/ M(N, [["render", I]]), R = _`
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
`, z = _`
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
  ${R}
`, B = _`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${R}
`, V = _`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${R}
`, H = _`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${R}
`, U = _`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
`, W = _`
  mutation PurgeWebhook($id: [ID!]!) {
    purgeWebhook(id: $id)
  }
`, G = {
	name: "WebhookList",
	components: { WebhookDialog: L },
	props: { panel: {
		type: Object,
		default: null
	} },
	inject: {
		apollo: {},
		confirm: {},
		messages: {},
		pluginAside: { default: null }
	},
	data() {
		let e = { status: null };
		return {
			dialog: !1,
			loading: !0,
			failed: !1,
			saving: !1,
			testing: !1,
			items: [],
			checked: /* @__PURE__ */ new Set(),
			events: [],
			selected: null,
			term: "",
			filter: this.pluginAside?.(() => this.asideContent, e) ?? e,
			secret: "",
			server: {
				enabled: !0,
				blocked: null
			},
			changes: 0,
			loadSeq: 0,
			left: !1,
			now: Date.now()
		};
	},
	setup() {
		return {
			mdiDeleteForever: b,
			mdiKeyVariant: S,
			mdiMagnify: C,
			mdiPencil: w,
			mdiRefresh: D,
			menuButton: {
				icon: x,
				variant: "text"
			},
			pausedColor: "color-mix(in srgb, rgb(var(--v-theme-warning)) 85%, rgb(var(--v-theme-on-surface)))"
		};
	},
	computed: {
		allChecked() {
			return this.filtered.length > 0 && this.filtered.every((e) => this.checked.has(e.id));
		},
		addButton() {
			return {
				title: this.$pgettext("webhooks", "Add webhook"),
				disabled: this.loading || !this.events.length,
				icon: E,
				class: "btn-add",
				color: "primary",
				variant: "tonal"
			};
		},
		serverText() {
			return this.server.enabled ? this.server.blocked ? this.$pgettext("webhooks", "All deliveries are blocked by the server configuration: %{reason}", { reason: this.reasonText(this.server.blocked) }) : "" : this.$pgettext("webhooks", "Webhooks are disabled by the server configuration, no events are sent");
		},
		filtered() {
			let e = (this.term ?? "").trim().toLocaleLowerCase();
			return this.items.filter((t) => this.filter.status !== null && t.status !== this.filter.status ? !1 : !e || t.name.toLocaleLowerCase().includes(e) || t.endpoint.toLocaleLowerCase().includes(e) || t.events.some((t) => t.toLocaleLowerCase().includes(e)));
		},
		asideContent() {
			return [{
				key: "status",
				title: this.$pgettext("webhooks", "Status"),
				open: !0,
				items: [
					{
						title: this.$pgettext("webhooks", "All"),
						icon: T,
						value: { status: null }
					},
					{
						title: this.$pgettext("webhooks", "Active"),
						icon: v,
						value: { status: !0 }
					},
					{
						title: this.$pgettext("webhooks", "Inactive"),
						icon: y,
						value: { status: !1 }
					}
				]
			}];
		}
	},
	created() {
		this.timer = null, this.$watch(() => [this.filter.status, this.term], () => this.checked = /* @__PURE__ */ new Set());
	},
	mounted() {
		this.load(), this.timer = setInterval(() => {
			this.items.some((e) => e.paused_until) && (this.now = Date.now());
		}, 6e4);
	},
	beforeUnmount() {
		clearInterval(this.timer), this.loadSeq++, this.left = !0;
	},
	methods: {
		async change(e, t) {
			if (!this.saving) {
				this.saving = !0;
				try {
					await e();
				} catch (e) {
					this.messages.error(t, e);
				} finally {
					this.saving = !1;
				}
			}
		},
		dateText(e) {
			return new Date(e).toLocaleString(this.$vuetify.locale.current);
		},
		errorText(e) {
			let t = e.last_error;
			if (!t) return this.$pgettext("webhooks", "None");
			let n = this.reasonText(t.reason, t.status);
			return t.at ? this.$pgettext("webhooks", "%{reason} · %{date}", {
				reason: n,
				date: this.dateText(t.at)
			}) : n;
		},
		async load() {
			let e = ++this.loadSeq, t = this.changes;
			this.loading = !0;
			try {
				let { data: n } = await this.apollo.query({
					query: z,
					fetchPolicy: "network-only"
				});
				if (e !== this.loadSeq) return;
				if (t !== this.changes) return this.load();
				this.items = n.cmsWebhooks, this.now = Date.now(), this.checked = /* @__PURE__ */ new Set(), this.failed = !1, this.selected &&= this.items.find((e) => e.id === this.selected.id) || this.selected, this.events = n.cmsWebhookEvents, this.server = n.cmsWebhookServer;
			} catch (t) {
				e === this.loadSeq && (this.failed = !0, this.messages.error(this.$pgettext("webhooks", "Error fetching webhooks"), t));
			} finally {
				e === this.loadSeq && (this.loading = !1);
			}
		},
		openAdd() {
			this.selected = null, this.secret = "", this.dialog = !0;
		},
		openEdit(e) {
			this.saving || (this.selected = e, this.secret = "", this.dialog = !0);
		},
		async ping(e) {
			if (!this.saving) {
				this.testing = !0;
				try {
					await this.change(async () => {
						let { data: t } = await this.apollo.mutate({
							mutation: U,
							variables: { id: e.id }
						}), n = t.pingWebhook;
						n.success ? (this.items = this.items.map((t) => t.id === e.id ? {
							...t,
							paused_until: null
						} : t), this.changes++, this.messages.add(this.$pgettext("webhooks", "Test event delivered (%{status})", { status: n.status }), "success")) : this.messages.add(this.$pgettext("webhooks", "Test event failed: %{reason}", { reason: this.reasonText(n.reason, n.status) }), "error");
					}, this.$pgettext("webhooks", "Test event failed"));
				} finally {
					this.testing = !1;
				}
			}
		},
		async purge(e = null) {
			let t = e ? [e] : this.items.filter((e) => this.checked.has(e.id));
			if (this.saving || !t.length || !await this.confirm.purge(t.map((e) => this.summary(e)))) return;
			let n = t.map((e) => e.id);
			await this.change(async () => {
				for (let e = 0; e < n.length; e += 100) {
					let t = new Set(n.slice(e, e + 100));
					await this.apollo.mutate({
						mutation: W,
						variables: { id: [...t] }
					}), this.items = this.items.filter((e) => !t.has(e.id)), this.changes++, this.checked = new Set([...this.checked].filter((e) => !t.has(e)));
				}
			}, this.$pgettext("webhooks", "Error purging webhook"));
		},
		put(e) {
			let t = new Set(this.checked);
			t.delete(e.id), this.items = this.items.some((t) => t.id === e.id) ? this.items.map((t) => t.id === e.id ? e : t) : [e, ...this.items], this.checked = t, this.changes++;
		},
		paused(e) {
			return !!e.paused_until && new Date(e.paused_until).getTime() > this.now;
		},
		reasonText(e, t) {
			return k(this.$pgettext, e, t);
		},
		reveal(e) {
			this.left ? this.messages.add(this.$pgettext("webhooks", "The new webhook secret could not be shown after leaving the page, please rotate it to get a new one"), "warning") : (this.secret = e, this.dialog = !0);
		},
		async rotate(e) {
			let t = this.$pgettext("webhooks", "Rotate the secret of this webhook? Receivers must be updated with the new secret.");
			!this.saving && await this.confirm.ask(this.$pgettext("webhooks", "Rotate"), t, [this.summary(e)]) && await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: H,
					variables: { id: e.id }
				});
				this.put(t.rotateWebhook.webhook), this.selected = t.rotateWebhook.webhook, this.reveal(t.rotateWebhook.secret);
			}, this.$pgettext("webhooks", "Error rotating webhook secret"));
		},
		async save(e) {
			let t = {
				name: e.name.trim(),
				events: e.events,
				status: e.status
			};
			await this.change(async () => {
				if (this.selected) {
					let { data: e } = await this.apollo.mutate({
						mutation: V,
						variables: {
							id: this.selected.id,
							input: t
						}
					});
					this.put(e.saveWebhook), this.dialog = !1;
				} else {
					let { data: n } = await this.apollo.mutate({
						mutation: B,
						variables: { input: {
							...t,
							url: e.url.trim()
						} }
					});
					this.put(n.addWebhook.webhook), this.reveal(n.addWebhook.secret);
				}
			}, this.$pgettext("webhooks", "Error saving webhook"));
		},
		successText(e) {
			return e.last_success_at ? this.dateText(e.last_success_at) : this.$pgettext("webhooks", "None");
		},
		summary(e) {
			return {
				name: e.name || e.endpoint,
				info: e.name ? e.endpoint : ""
			};
		},
		toggle() {
			this.checked = this.allChecked ? /* @__PURE__ */ new Set() : new Set(this.filtered.map((e) => e.id));
		},
		toggleCheck(e) {
			let t = new Set(this.checked);
			t.delete(e.id) || t.add(e.id), this.checked = t;
		}
	}
}, K = { class: "text-medium-emphasis mb-4" }, q = { class: "header" }, J = { class: "bulk" }, Y = { class: "search" }, X = { class: "layout" }, te = { class: "d-flex align-center w-100" }, Z = { class: "d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2" }, Q = ["aria-disabled", "onClick"], ne = { class: "item-text" }, re = { class: "item-head" }, ie = { class: "item-title" }, ae = {
	key: 0,
	class: "item-subtitle item-endpoint"
}, oe = { class: "item-subtitle" }, se = { class: "item-aux text-end" }, $ = { class: "item-subtitle" }, ce = { class: "item-subtitle" }, le = {
	key: 1,
	class: "loading"
}, ue = {
	key: 2,
	class: "notfound load-error"
}, de = {
	key: 3,
	class: "notfound"
}, fe = { class: "btn-group" };
function pe(a, h, _, v, y, b) {
	let x = f("v-alert"), S = f("v-checkbox-btn"), C = f("v-btn"), w = f("v-list-item"), T = f("CmsActionMenu"), E = f("v-text-field"), D = f("v-divider"), O = f("v-chip"), k = f("v-list"), A = f("CmsLoadingSpinner"), j = f("v-sheet"), M = f("v-container"), N = f("WebhookDialog");
	return u(), r(e, null, [s(M, { class: "webhook-list" }, {
		default: m(() => [s(j, { class: "box scroll" }, {
			default: m(() => [
				i("p", K, p(a.$pgettext("webhooks", "Send signed notifications when published content changes.")), 1),
				b.serverText ? (u(), t(x, {
					key: 0,
					type: "warning",
					variant: "tonal",
					class: "webhook-server mb-4"
				}, {
					default: m(() => [o(p(b.serverText), 1)]),
					_: 1
				})) : n("", !0),
				i("div", q, [
					i("div", J, [
						s(S, {
							"model-value": b.allChecked,
							indeterminate: y.checked.size > 0 && !b.allChecked,
							onClick: g(b.toggle, ["stop"]),
							"aria-label": a.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"indeterminate",
							"onClick",
							"aria-label"
						]),
						s(T, null, {
							activator: m(({ props: e, label: t }) => [s(C, c({
								...e,
								...v.menuButton
							}, {
								disabled: !y.checked.size,
								title: t
							}), null, 16, ["disabled", "title"])]),
							default: m(() => [s(w, null, {
								default: m(() => [s(C, {
									"prepend-icon": v.mdiDeleteForever,
									disabled: y.saving,
									variant: "text",
									onClick: h[0] ||= (e) => b.purge()
								}, {
									default: m(() => [o(p(a.$pgettext("webhooks", "Purge (%{count})", { count: y.checked.size })), 1)]),
									_: 1
								}, 8, ["prepend-icon", "disabled"])]),
								_: 1
							})]),
							_: 1
						}),
						s(C, c(b.addButton, { onClick: b.openAdd }), null, 16, ["onClick"])
					]),
					i("div", Y, [s(E, {
						modelValue: y.term,
						"onUpdate:modelValue": h[1] ||= (e) => y.term = e,
						"prepend-inner-icon": v.mdiMagnify,
						label: a.$pgettext("webhooks", "Search for"),
						variant: "underlined",
						"hide-details": "",
						clearable: ""
					}, null, 8, [
						"modelValue",
						"prepend-inner-icon",
						"label"
					])]),
					i("div", X, [s(C, {
						title: a.$pgettext("webhooks", "Refresh"),
						icon: v.mdiRefresh,
						loading: y.loading,
						class: "btn-reload",
						variant: "text",
						onClick: b.load
					}, null, 8, [
						"title",
						"icon",
						"loading",
						"onClick"
					])])
				]),
				s(k, {
					class: "items",
					role: "list"
				}, {
					default: m(() => [(u(!0), r(e, null, d(b.filtered, (e) => (u(), t(w, {
						key: e.id,
						class: "border-b rounded-0 pa-1",
						role: "listitem"
					}, {
						default: m(() => [i("div", te, [i("div", Z, [s(S, {
							"model-value": y.checked.has(e.id),
							"onUpdate:modelValue": (t) => b.toggleCheck(e),
							"aria-label": a.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onUpdate:modelValue",
							"aria-label"
						]), s(T, null, {
							activator: m(({ props: e, label: t }) => [s(C, c({ ref_for: !0 }, {
								...e,
								...v.menuButton
							}, { title: t }), null, 16, ["title"])]),
							default: m(() => [
								s(w, null, {
									default: m(() => [s(C, {
										"prepend-icon": v.mdiPencil,
										disabled: y.saving,
										variant: "text",
										onClick: (t) => b.openEdit(e)
									}, {
										default: m(() => [o(p(a.$pgettext("webhooks", "Edit")), 1)]),
										_: 1
									}, 8, [
										"prepend-icon",
										"disabled",
										"onClick"
									])]),
									_: 2
								}, 1024),
								s(w, null, {
									default: m(() => [s(C, {
										"prepend-icon": v.mdiKeyVariant,
										disabled: y.saving,
										class: "btn-rotate",
										variant: "text",
										onClick: (t) => b.rotate(e)
									}, {
										default: m(() => [o(p(a.$pgettext("webhooks", "Rotate")), 1)]),
										_: 1
									}, 8, [
										"prepend-icon",
										"disabled",
										"onClick"
									])]),
									_: 2
								}, 1024),
								s(D),
								s(w, null, {
									default: m(() => [s(C, {
										"prepend-icon": v.mdiDeleteForever,
										disabled: y.saving,
										variant: "text",
										onClick: (t) => b.purge(e)
									}, {
										default: m(() => [o(p(a.$pgettext("webhooks", "Purge")), 1)]),
										_: 1
									}, 8, [
										"prepend-icon",
										"disabled",
										"onClick"
									])]),
									_: 2
								}, 1024)
							]),
							_: 2
						}, 1024)]), i("a", {
							href: "#",
							class: "item-content",
							"aria-disabled": y.saving,
							style: l(y.saving ? {
								opacity: .6,
								cursor: "default"
							} : null),
							onClick: g((t) => b.openEdit(e), ["prevent"])
						}, [i("div", ne, [
							i("div", re, [i("span", ie, p(e.name || e.endpoint), 1)]),
							e.name ? (u(), r("div", ae, p(e.endpoint), 1)) : n("", !0),
							i("div", oe, p(e.events.join(", ")), 1)
						]), i("div", se, [
							i("div", null, [s(O, {
								color: e.status ? "success" : void 0,
								size: "small"
							}, {
								default: m(() => [o(p(e.status ? a.$pgettext("webhooks", "Active") : a.$pgettext("webhooks", "Inactive")), 1)]),
								_: 2
							}, 1032, ["color"])]),
							i("div", $, p(a.$pgettext("webhooks", "Last success: %{date}", { date: b.successText(e) })), 1),
							i("div", ce, p(a.$pgettext("webhooks", "Last error: %{reason}", { reason: b.errorText(e) })), 1),
							b.paused(e) ? (u(), r("div", {
								key: 0,
								class: "item-subtitle webhook-paused",
								style: l({ color: v.pausedColor })
							}, p(a.$pgettext("webhooks", "Paused until: %{date}", { date: b.dateText(e.paused_until) })), 5)) : n("", !0)
						])], 12, Q)])]),
						_: 2
					}, 1024))), 128))]),
					_: 1
				}),
				y.loading ? (u(), r("p", le, [o(p(a.$pgettext("webhooks", "Loading")) + " ", 1), s(A, {
					width: "32",
					height: "32"
				})])) : y.failed && !y.items.length ? (u(), r("p", ue, [o(p(a.$pgettext("webhooks", "Error fetching webhooks")) + " ", 1), s(C, {
					class: "btn-retry",
					variant: "text",
					onClick: b.load
				}, {
					default: m(() => [o(p(a.$pgettext("webhooks", "Refresh")), 1)]),
					_: 1
				}, 8, ["onClick"])])) : b.filtered.length ? n("", !0) : (u(), r("p", de, p(y.items.length ? a.$pgettext("webhooks", "No entries found") : a.$pgettext("webhooks", "No webhooks configured.")), 1)),
				i("div", fe, [s(C, c(b.addButton, { onClick: b.openAdd }), null, 16, ["onClick"])])
			]),
			_: 1
		})]),
		_: 1
	}), s(N, {
		modelValue: y.dialog,
		"onUpdate:modelValue": h[2] ||= (e) => y.dialog = e,
		webhook: y.selected,
		events: y.events,
		secret: y.secret,
		saving: y.saving,
		testing: y.testing,
		testable: y.server.enabled && y.server.blocked !== "invalid_policy",
		onClosed: h[3] ||= (e) => y.secret = "",
		onSave: h[4] ||= (e) => b.save(e),
		onTest: h[5] ||= (e) => b.ping(e)
	}, null, 8, [
		"modelValue",
		"webhook",
		"events",
		"secret",
		"saving",
		"testing",
		"testable"
	])], 64);
}
var me = /*#__PURE__*/ M(G, [["render", pe]]);
export { me as default };
