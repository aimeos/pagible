import { Fragment as e, createBlock as t, createCommentVNode as n, createElementBlock as r, createElementVNode as i, createTextVNode as a, createVNode as o, mergeProps as s, openBlock as c, renderList as l, resolveComponent as u, toDisplayString as d, withCtx as f, withModifiers as p } from "vue";
import m from "graphql-tag";
//#region node_modules/@mdi/js/mdi.js
var h = "M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20M16.59 7.58L10 14.17L7.41 11.59L6 13L10 17L18 9L16.59 7.58Z", g = "M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22C17.53,22 22,17.53 22,12C22,6.47 17.53,2 12,2M14.59,8L12,10.59L9.41,8L8,9.41L10.59,12L8,14.59L9.41,16L12,13.41L14.59,16L16,14.59L13.41,12L16,9.41L14.59,8Z", _ = "M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M8.46,11.88L9.87,10.47L12,12.59L14.12,10.47L15.53,11.88L13.41,14L15.53,16.12L14.12,17.53L12,15.41L9.88,17.53L8.47,16.12L10.59,14L8.46,11.88M15.5,4L14.5,3H9.5L8.5,4H5V6H19V4H15.5Z", v = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", y = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", b = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", x = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", S = "M14 10H3V12H14V10M14 6H3V8H14V6M3 16H10V14H3V16M21.5 11.5L23 13L16 20L11.5 15.5L13 14L16 17L21.5 11.5Z", C = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", w = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", T = () => ({
	url: "",
	name: "",
	events: [],
	status: !1
});
function E(e, t, n = null) {
	let r = {
		connection_failed: e("webhooks", "Connection failed"),
		destination_not_allowed: e("webhooks", "Access denied"),
		invalid_encryption: e("webhooks", "Secret can't be decrypted, rotate it"),
		invalid_policy: e("webhooks", "Blocked by server configuration"),
		invalid_url: e("webhooks", "Not a valid URL"),
		queue_failed: e("webhooks", "Queue unavailable"),
		resolution_failed: e("webhooks", "Host not found"),
		response_headers_too_large: e("webhooks", "Response too large"),
		timeout: e("webhooks", "Request timed out")
	}, i = t === "http_error" && n >= 300 && n < 400 ? e("webhooks", "Redirects aren't followed") : r[t] || e("webhooks", "Delivery failed");
	return n ? `${i} (${n})` : i;
}
function D(e) {
	return e?.last_error?.reason === "invalid_encryption";
}
function O(e) {
	let t = (e ?? "").trim();
	if (!/^https:\/\/\S+$/i.test(t)) return !1;
	try {
		return !!new URL(t).hostname;
	} catch {
		return !1;
	}
}
//#endregion
//#region \0plugin-vue:export-helper
var k = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, A = {
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
		enabled: {
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
		return { form: T() };
	},
	setup() {
		return {
			reasonText: E,
			undecryptable: D,
			validUrl: O
		};
	},
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
			} : T();
		},
		save() {
			let e = this.form;
			e.events.length && (this.webhook || O(e.url)) && this.$emit("save", e);
		}
	},
	watch: { modelValue: {
		immediate: !0,
		handler(e) {
			e && this.reset();
		}
	} }
}, j = { class: "webhook-status" }, M = { class: "webhook-status-label label d-flex align-center font-weight-bold mb-1" }, N = { class: "webhook-current on-surface text-break mt-4 mb-4" };
function ee(s, l, p, m, h, g) {
	let _ = u("v-alert"), v = u("v-text-field"), y = u("v-switch"), b = u("v-select"), x = u("v-btn"), S = u("CmsDialog");
	return c(), t(S, {
		"model-value": p.modelValue,
		title: p.secret ? s.$pgettext("webhooks", "Webhook secret") : p.webhook ? s.$pgettext("webhooks", "Edit webhook") : s.$pgettext("webhooks", "Add webhook"),
		persistent: !!p.secret,
		"max-width": "640",
		"onUpdate:modelValue": l[6] ||= (e) => s.$emit("update:modelValue", e),
		onAfterLeave: l[7] ||= (e) => s.$emit("closed")
	}, {
		actions: f(({ close: i }) => [p.secret ? (c(), r(e, { key: 0 }, [o(x, {
			variant: "text",
			onClick: i
		}, {
			default: f(() => [a(d(s.$pgettext("webhooks", "Done")), 1)]),
			_: 1
		}, 8, ["onClick"]), o(x, {
			color: "primary",
			variant: "tonal",
			onClick: g.copySecret
		}, {
			default: f(() => [a(d(s.$pgettext("webhooks", "Copy secret")), 1)]),
			_: 1
		}, 8, ["onClick"])], 64)) : (c(), r(e, { key: 1 }, [
			p.webhook ? (c(), t(x, {
				key: 0,
				class: "btn-test order-first",
				variant: "outlined",
				disabled: p.saving || !p.enabled,
				loading: p.testing,
				onClick: l[4] ||= (e) => s.$emit("test", p.webhook),
				active: ""
			}, {
				default: f(() => [a(d(s.$pgettext("webhooks", "Test")), 1)]),
				_: 1
			}, 8, ["disabled", "loading"])) : n("", !0),
			o(x, {
				variant: "text",
				onClick: i
			}, {
				default: f(() => [a(d(s.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]),
			o(x, {
				color: "primary",
				variant: "tonal",
				disabled: p.testing || !h.form.events.length || !p.webhook && !m.validUrl(h.form.url),
				loading: p.saving && !p.testing,
				onClick: l[5] ||= (e) => g.save()
			}, {
				default: f(() => [a(d(s.$pgettext("webhooks", "Save")), 1)]),
				_: 1
			}, 8, ["disabled", "loading"])
		], 64))]),
		default: f(() => [p.secret ? (c(), r(e, { key: 0 }, [o(_, {
			type: "warning",
			variant: "tonal",
			class: "mb-4"
		}, {
			default: f(() => [a(d(s.$pgettext("webhooks", "Copy this secret now. It will not be shown again.")), 1)]),
			_: 1
		}), o(v, {
			"model-value": p.secret,
			class: "webhook-secret",
			variant: "underlined",
			readonly: ""
		}, null, 8, ["model-value"])], 64)) : (c(), r(e, { key: 1 }, [
			i("div", j, [i("div", M, d(s.$pgettext("webhooks", "Active")), 1), o(y, {
				modelValue: h.form.status,
				"onUpdate:modelValue": l[0] ||= (e) => h.form.status = e,
				"aria-label": s.$pgettext("webhooks", "Active"),
				hint: s.$pgettext("webhooks", "Inactive webhooks receive no events and their queued deliveries are dropped"),
				color: "primary",
				"hide-details": "auto",
				inset: ""
			}, null, 8, [
				"modelValue",
				"aria-label",
				"hint"
			])]),
			p.webhook ? (c(), r(e, { key: 1 }, [i("p", N, d(p.webhook.endpoint), 1), m.undecryptable(p.webhook) ? (c(), t(_, {
				key: 0,
				type: "warning",
				variant: "tonal",
				class: "webhook-undecryptable mb-4"
			}, {
				default: f(() => [a(d(m.reasonText(s.$pgettext, "invalid_encryption")), 1)]),
				_: 1
			})) : n("", !0)], 64)) : (c(), t(v, {
				key: 0,
				modelValue: h.form.url,
				"onUpdate:modelValue": l[1] ||= (e) => h.form.url = e,
				label: s.$pgettext("webhooks", "HTTPS endpoint URL"),
				hint: s.$pgettext("webhooks", "Address receiving the signed events, it cannot be changed later"),
				rules: [(e) => m.validUrl(e) || s.$pgettext("webhooks", "Not a valid URL")],
				"validate-on": "invalid-input",
				variant: "underlined",
				maxlength: "500",
				autofocus: ""
			}, null, 8, [
				"modelValue",
				"label",
				"hint",
				"rules"
			])),
			o(b, {
				modelValue: h.form.events,
				"onUpdate:modelValue": l[2] ||= (e) => h.form.events = e,
				items: p.events,
				label: s.$pgettext("webhooks", "Events"),
				hint: s.$pgettext("webhooks", "Events sent to the webhook, queued deliveries of removed events are cancelled"),
				variant: "underlined",
				multiple: "",
				chips: ""
			}, null, 8, [
				"modelValue",
				"items",
				"label",
				"hint"
			]),
			o(v, {
				modelValue: h.form.name,
				"onUpdate:modelValue": l[3] ||= (e) => h.form.name = e,
				label: s.$pgettext("webhooks", "Name"),
				hint: s.$pgettext("webhooks", "Optional name to tell webhooks with the same endpoint apart, visible to all webhook editors"),
				class: "webhook-name",
				variant: "underlined",
				maxlength: "100"
			}, null, 8, [
				"modelValue",
				"label",
				"hint"
			])
		], 64))]),
		_: 1
	}, 8, [
		"model-value",
		"title",
		"persistent"
	]);
}
var P = /*#__PURE__*/ k(A, [["render", ee]]), F = m`
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
`, I = m`
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
  ${F}
`, L = m`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${F}
`, R = m`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${F}
`, z = m`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${F}
`, B = m`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
`, V = m`
  mutation PurgeWebhook($id: [ID!]!) {
    purgeWebhook(id: $id)
  }
`, H = {
	name: "WebhookList",
	components: { WebhookDialog: P },
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
			saving: !1,
			testing: !1,
			items: [],
			checked: /* @__PURE__ */ new Set(),
			names: [],
			selected: null,
			term: "",
			filter: this.pluginAside?.(() => this.asideContent, e) ?? e,
			secret: "",
			server: {
				enabled: !0,
				blocked: null
			}
		};
	},
	setup() {
		return {
			mdiDeleteForever: _,
			mdiDotsVertical: v,
			mdiKeyVariant: y,
			mdiMagnify: b,
			mdiPencil: x,
			mdiPlus: C,
			mdiRefresh: w
		};
	},
	computed: {
		serverText() {
			return this.server.enabled ? this.server.blocked ? this.$pgettext("webhooks", "All deliveries are blocked by the server configuration") : "" : this.$pgettext("webhooks", "Webhooks are disabled by the server configuration, no events are sent");
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
						icon: S,
						value: { status: null }
					},
					{
						title: this.$pgettext("webhooks", "Active"),
						icon: h,
						value: { status: !0 }
					},
					{
						title: this.$pgettext("webhooks", "Inactive"),
						icon: g,
						value: { status: !1 }
					}
				]
			}];
		}
	},
	created() {
		this.$watch(() => [this.filter.status, this.term], () => this.checked = /* @__PURE__ */ new Set());
	},
	mounted() {
		this.load();
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
			return t.at ? `${n} · ${this.dateText(t.at)}` : n;
		},
		async load() {
			this.loading = !0;
			try {
				let { data: e } = await this.apollo.query({
					query: I,
					fetchPolicy: "network-only"
				});
				this.items = e.cmsWebhooks, this.checked = /* @__PURE__ */ new Set(), this.names = e.cmsWebhookEvents, this.server = e.cmsWebhookServer;
			} catch (e) {
				this.messages.error(this.$pgettext("webhooks", "Error fetching webhooks"), e);
			} finally {
				this.loading = !1;
			}
		},
		openAdd() {
			this.selected = null, this.secret = "", this.dialog = !0;
		},
		openEdit(e) {
			this.selected = e, this.secret = "", this.dialog = !0;
		},
		async ping(e) {
			this.testing = !0;
			try {
				await this.change(async () => {
					let { data: t } = await this.apollo.mutate({
						mutation: B,
						variables: { id: e.id }
					}), n = t.pingWebhook;
					n.success ? (this.items = this.items.map((t) => t.id === e.id ? {
						...t,
						paused_until: null
					} : t), this.messages.add(this.$pgettext("webhooks", "Test event delivered") + ` (${n.status})`, "success")) : this.messages.add(this.$pgettext("webhooks", "Test event failed") + ": " + this.reasonText(n.reason, n.status), "error");
				}, this.$pgettext("webhooks", "Test event failed"));
			} finally {
				this.testing = !1;
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
						mutation: V,
						variables: { id: [...t] }
					}), this.items = this.items.filter((e) => !t.has(e.id)), this.checked = new Set([...this.checked].filter((e) => !t.has(e)));
				}
			}, this.$pgettext("webhooks", "Error purging webhook"));
		},
		put(e) {
			let t = new Set(this.checked);
			t.delete(e.id), this.items = this.items.some((t) => t.id === e.id) ? this.items.map((t) => t.id === e.id ? e : t) : [e, ...this.items], this.checked = t;
		},
		reasonText(e, t) {
			return E(this.$pgettext, e, t);
		},
		async rotate(e) {
			let t = this.$pgettext("webhooks", "Rotate the secret of this webhook? Receivers must be updated with the new secret.");
			!this.saving && await this.confirm.ask(this.$pgettext("webhooks", "Rotate"), t, [this.summary(e)]) && await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: z,
					variables: { id: e.id }
				});
				this.put(t.rotateWebhook.webhook), this.secret = t.rotateWebhook.secret, this.dialog = !0;
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
						mutation: R,
						variables: {
							id: this.selected.id,
							input: t
						}
					});
					this.put(e.saveWebhook), this.dialog = !1;
				} else {
					let { data: n } = await this.apollo.mutate({
						mutation: L,
						variables: { input: {
							...t,
							url: e.url.trim()
						} }
					});
					this.put(n.addWebhook.webhook), this.secret = n.addWebhook.secret;
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
			this.checked = this.checked.size ? /* @__PURE__ */ new Set() : new Set(this.filtered.map((e) => e.id));
		},
		toggleCheck(e) {
			let t = new Set(this.checked);
			t.delete(e.id) || t.add(e.id), this.checked = t;
		}
	}
}, U = { class: "text-medium-emphasis mb-4" }, W = { class: "header" }, G = { class: "bulk" }, K = { class: "search" }, q = { class: "layout" }, J = { class: "d-flex align-center w-100" }, Y = { class: "d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2" }, X = ["onClick"], Z = { class: "item-text" }, Q = { class: "item-head" }, te = { class: "item-title" }, ne = {
	key: 0,
	class: "item-subtitle item-endpoint"
}, re = { class: "item-subtitle" }, ie = { class: "item-aux text-end" }, ae = { class: "item-subtitle" }, $ = { class: "item-subtitle" }, oe = {
	key: 0,
	class: "item-subtitle webhook-paused"
}, se = {
	key: 1,
	class: "loading"
}, ce = {
	key: 2,
	class: "notfound"
}, le = { class: "btn-group" };
function ue(m, h, g, _, v, y) {
	let b = u("v-alert"), x = u("v-checkbox-btn"), S = u("v-btn"), C = u("v-list-item"), w = u("CmsActionMenu"), T = u("v-text-field"), E = u("v-divider"), D = u("v-chip"), O = u("v-list"), k = u("CmsLoadingSpinner"), A = u("v-sheet"), j = u("v-container"), M = u("WebhookDialog");
	return c(), r(e, null, [o(j, { class: "webhook-list" }, {
		default: f(() => [o(A, { class: "box scroll" }, {
			default: f(() => [
				i("p", U, d(m.$pgettext("webhooks", "Send signed notifications when published content changes.")), 1),
				y.serverText ? (c(), t(b, {
					key: 0,
					type: "warning",
					variant: "tonal",
					class: "webhook-server mb-4"
				}, {
					default: f(() => [a(d(y.serverText), 1)]),
					_: 1
				})) : n("", !0),
				i("div", W, [
					i("div", G, [
						o(x, {
							"model-value": v.checked.size > 0,
							onClick: p(y.toggle, ["stop"]),
							"aria-label": m.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onClick",
							"aria-label"
						]),
						o(w, null, {
							activator: f(({ props: e, label: t }) => [o(S, s(e, {
								disabled: !v.checked.size,
								title: t,
								icon: _.mdiDotsVertical,
								variant: "text"
							}), null, 16, [
								"disabled",
								"title",
								"icon"
							])]),
							default: f(() => [o(C, null, {
								default: f(() => [o(S, {
									"prepend-icon": _.mdiDeleteForever,
									disabled: v.saving,
									variant: "text",
									onClick: h[0] ||= (e) => y.purge()
								}, {
									default: f(() => [a(d(m.$pgettext("webhooks", "Purge")) + " (" + d(v.checked.size) + ")", 1)]),
									_: 1
								}, 8, ["prepend-icon", "disabled"])]),
								_: 1
							})]),
							_: 1
						}),
						o(S, {
							title: m.$pgettext("webhooks", "Add webhook"),
							disabled: v.loading,
							icon: _.mdiPlus,
							class: "btn-add",
							color: "primary",
							variant: "tonal",
							onClick: y.openAdd
						}, null, 8, [
							"title",
							"disabled",
							"icon",
							"onClick"
						])
					]),
					i("div", K, [o(T, {
						modelValue: v.term,
						"onUpdate:modelValue": h[1] ||= (e) => v.term = e,
						"prepend-inner-icon": _.mdiMagnify,
						label: m.$pgettext("webhooks", "Search for"),
						variant: "underlined",
						"hide-details": "",
						clearable: ""
					}, null, 8, [
						"modelValue",
						"prepend-inner-icon",
						"label"
					])]),
					i("div", q, [o(S, {
						title: m.$pgettext("webhooks", "Refresh"),
						icon: _.mdiRefresh,
						loading: v.loading,
						class: "btn-reload",
						variant: "text",
						onClick: y.load
					}, null, 8, [
						"title",
						"icon",
						"loading",
						"onClick"
					])])
				]),
				o(O, {
					class: "items",
					role: "list"
				}, {
					default: f(() => [(c(!0), r(e, null, l(y.filtered, (e) => (c(), t(C, {
						key: e.id,
						class: "border-b rounded-0 pa-1",
						role: "listitem"
					}, {
						default: f(() => [i("div", J, [i("div", Y, [o(x, {
							"model-value": v.checked.has(e.id),
							"onUpdate:modelValue": (t) => y.toggleCheck(e),
							"aria-label": m.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onUpdate:modelValue",
							"aria-label"
						]), o(w, null, {
							activator: f(({ props: e, label: t }) => [o(S, s({ ref_for: !0 }, e, {
								title: t,
								icon: _.mdiDotsVertical,
								variant: "text"
							}), null, 16, ["title", "icon"])]),
							default: f(() => [
								o(C, null, {
									default: f(() => [o(S, {
										"prepend-icon": _.mdiPencil,
										variant: "text",
										onClick: (t) => y.openEdit(e)
									}, {
										default: f(() => [a(d(m.$pgettext("webhooks", "Edit")), 1)]),
										_: 1
									}, 8, ["prepend-icon", "onClick"])]),
									_: 2
								}, 1024),
								o(C, null, {
									default: f(() => [o(S, {
										"prepend-icon": _.mdiKeyVariant,
										disabled: v.saving,
										class: "btn-rotate",
										variant: "text",
										onClick: (t) => y.rotate(e)
									}, {
										default: f(() => [a(d(m.$pgettext("webhooks", "Rotate")), 1)]),
										_: 1
									}, 8, [
										"prepend-icon",
										"disabled",
										"onClick"
									])]),
									_: 2
								}, 1024),
								o(E),
								o(C, null, {
									default: f(() => [o(S, {
										"prepend-icon": _.mdiDeleteForever,
										disabled: v.saving,
										variant: "text",
										onClick: (t) => y.purge(e)
									}, {
										default: f(() => [a(d(m.$pgettext("webhooks", "Purge")), 1)]),
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
							onClick: p((t) => y.openEdit(e), ["prevent"])
						}, [i("div", Z, [
							i("div", Q, [i("span", te, d(e.name || e.endpoint), 1)]),
							e.name ? (c(), r("div", ne, d(e.endpoint), 1)) : n("", !0),
							i("div", re, d(e.events.join(", ")), 1)
						]), i("div", ie, [
							i("div", null, [o(D, {
								color: e.status ? "success" : void 0,
								size: "small"
							}, {
								default: f(() => [a(d(e.status ? m.$pgettext("webhooks", "Active") : m.$pgettext("webhooks", "Inactive")), 1)]),
								_: 2
							}, 1032, ["color"])]),
							i("div", ae, d(m.$pgettext("webhooks", "Last success")) + ": " + d(y.successText(e)), 1),
							i("div", $, d(m.$pgettext("webhooks", "Last error")) + ": " + d(y.errorText(e)), 1),
							e.paused_until ? (c(), r("div", oe, d(m.$pgettext("webhooks", "Paused until")) + ": " + d(y.dateText(e.paused_until)), 1)) : n("", !0)
						])], 8, X)])]),
						_: 2
					}, 1024))), 128))]),
					_: 1
				}),
				v.loading ? (c(), r("p", se, [a(d(m.$pgettext("webhooks", "Loading")) + " ", 1), o(k, {
					width: "32",
					height: "32"
				})])) : y.filtered.length ? n("", !0) : (c(), r("p", ce, d(v.items.length ? m.$pgettext("webhooks", "No entries found") : m.$pgettext("webhooks", "No webhooks configured.")), 1)),
				i("div", le, [o(S, {
					title: m.$pgettext("webhooks", "Add webhook"),
					disabled: v.loading,
					icon: _.mdiPlus,
					class: "btn-add",
					color: "primary",
					variant: "tonal",
					onClick: y.openAdd
				}, null, 8, [
					"title",
					"disabled",
					"icon",
					"onClick"
				])])
			]),
			_: 1
		})]),
		_: 1
	}), o(M, {
		modelValue: v.dialog,
		"onUpdate:modelValue": h[2] ||= (e) => v.dialog = e,
		webhook: v.selected,
		events: v.names,
		secret: v.secret,
		saving: v.saving,
		testing: v.testing,
		enabled: v.server.enabled,
		onClosed: h[3] ||= (e) => v.secret = "",
		onSave: h[4] ||= (e) => y.save(e),
		onTest: h[5] ||= (e) => y.ping(e)
	}, null, 8, [
		"modelValue",
		"webhook",
		"events",
		"secret",
		"saving",
		"testing",
		"enabled"
	])], 64);
}
var de = /*#__PURE__*/ k(H, [["render", ue], ["__scopeId", "data-v-1458bf44"]]);
//#endregion
export { de as default };
