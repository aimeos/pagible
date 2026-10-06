import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, mergeProps as c, openBlock as l, renderList as u, resolveComponent as d, toDisplayString as f, withCtx as p, withModifiers as m } from "vue";
//#region node_modules/@mdi/js/mdi.js
var h = "M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20M16.59 7.58L10 14.17L7.41 11.59L6 13L10 17L18 9L16.59 7.58Z", g = "M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22C17.53,22 22,17.53 22,12C22,6.47 17.53,2 12,2M14.59,8L12,10.59L9.41,8L8,9.41L10.59,12L8,14.59L9.41,16L12,13.41L14.59,16L16,14.59L13.41,12L16,9.41L14.59,8Z", _ = "M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M8.46,11.88L9.87,10.47L12,12.59L14.12,10.47L15.53,11.88L13.41,14L15.53,16.12L14.12,17.53L12,15.41L9.88,17.53L8.47,16.12L10.59,14L8.46,11.88M15.5,4L14.5,3H9.5L8.5,4H5V6H19V4H15.5Z", v = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", y = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", b = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", x = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", S = "M14 10H3V12H14V10M14 6H3V8H14V6M3 16H10V14H3V16M21.5 11.5L23 13L16 20L11.5 15.5L13 14L16 17L21.5 11.5Z", C = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", w = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", T = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, E = e`
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
`, D = e`
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
  ${E}
`, O = e`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${E}
`, k = e`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${E}
`, A = e`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${E}
`, j = e`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
`, M = e`
  mutation PurgeWebhook($id: [ID!]!) {
    purgeWebhook(id: $id)
  }
`, N = () => ({
	url: "",
	name: "",
	events: [],
	status: !1
}), P = {
	name: "WebhookList",
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
			form: N(),
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
		async copySecret() {
			try {
				await navigator.clipboard.writeText(this.secret), this.messages.add(this.$pgettext("webhooks", "Secret copied"), "success");
			} catch {
				this.messages.add(this.$pgettext("webhooks", "Unable to copy secret"), "error");
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
					query: D,
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
			this.selected = null, this.form = N(), this.secret = "", this.dialog = !0;
		},
		openEdit(e) {
			this.selected = e, this.form = {
				url: "",
				name: e.name,
				events: [...e.events],
				status: e.status
			}, this.secret = "", this.dialog = !0;
		},
		async ping(e) {
			this.testing = !0;
			try {
				await this.change(async () => {
					let { data: t } = await this.apollo.mutate({
						mutation: j,
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
						mutation: M,
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
			let n = {
				connection_failed: this.$pgettext("webhooks", "Connection failed"),
				destination_not_allowed: this.$pgettext("webhooks", "Access denied"),
				invalid_encryption: this.$pgettext("webhooks", "Secret can't be decrypted, rotate it"),
				invalid_policy: this.$pgettext("webhooks", "Blocked by server configuration"),
				invalid_url: this.$pgettext("webhooks", "Not a valid URL"),
				queue_failed: this.$pgettext("webhooks", "Queue unavailable"),
				resolution_failed: this.$pgettext("webhooks", "Host not found"),
				response_headers_too_large: this.$pgettext("webhooks", "Response too large"),
				timeout: this.$pgettext("webhooks", "Request timed out")
			}, r = e === "http_error" && t >= 300 && t < 400 ? this.$pgettext("webhooks", "Redirects aren't followed") : n[e] || this.$pgettext("webhooks", "Delivery failed");
			return t ? `${r} (${t})` : r;
		},
		async rotate(e) {
			let t = this.$pgettext("webhooks", "Rotate the secret of this webhook? Receivers must be updated with the new secret.");
			!this.saving && await this.confirm.ask(this.$pgettext("webhooks", "Rotate"), t, [this.summary(e)]) && await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: A,
					variables: { id: e.id }
				});
				this.put(t.rotateWebhook.webhook), this.secret = t.rotateWebhook.secret, this.dialog = !0;
			}, this.$pgettext("webhooks", "Error rotating webhook secret"));
		},
		async save() {
			let e = this.form;
			if (!e.events.length || !this.selected && !this.validUrl(e.url)) return;
			let t = {
				name: e.name.trim(),
				events: e.events,
				status: e.status
			};
			await this.change(async () => {
				if (this.selected) {
					let { data: e } = await this.apollo.mutate({
						mutation: k,
						variables: {
							id: this.selected.id,
							input: t
						}
					});
					this.put(e.saveWebhook), this.dialog = !1;
				} else {
					let { data: n } = await this.apollo.mutate({
						mutation: O,
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
		},
		undecryptable(e) {
			return e?.last_error?.reason === "invalid_encryption";
		},
		validUrl(e) {
			let t = (e ?? "").trim();
			if (!/^https:\/\/\S+$/i.test(t)) return !1;
			try {
				return !!new URL(t).hostname;
			} catch {
				return !1;
			}
		}
	}
}, F = { class: "text-medium-emphasis mb-4" }, I = { class: "header" }, L = { class: "bulk" }, R = { class: "search" }, z = { class: "layout" }, B = { class: "d-flex align-center w-100" }, V = { class: "d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2" }, H = ["onClick"], U = { class: "item-text" }, W = { class: "item-head" }, G = { class: "item-title" }, K = {
	key: 0,
	class: "item-subtitle item-endpoint"
}, q = { class: "item-subtitle" }, J = { class: "item-aux text-end" }, Y = { class: "item-subtitle" }, X = { class: "item-subtitle" }, Z = {
	key: 0,
	class: "item-subtitle webhook-paused"
}, Q = {
	key: 1,
	class: "loading"
}, $ = {
	key: 2,
	class: "notfound"
}, ee = { class: "btn-group" }, te = { class: "webhook-status" }, ne = { class: "webhook-status-label label d-flex align-center font-weight-bold mb-1" }, re = { class: "webhook-current on-surface text-break mt-4 mb-4" };
function ie(e, h, g, _, v, y) {
	let b = d("v-alert"), x = d("v-checkbox-btn"), S = d("v-btn"), C = d("v-list-item"), w = d("CmsActionMenu"), T = d("v-text-field"), E = d("v-divider"), D = d("v-chip"), O = d("v-list"), k = d("CmsLoadingSpinner"), A = d("v-sheet"), j = d("v-container"), M = d("v-switch"), N = d("v-select"), P = d("CmsDialog");
	return l(), i(t, null, [s(j, { class: "webhook-list" }, {
		default: p(() => [s(A, { class: "box scroll" }, {
			default: p(() => [
				a("p", F, f(e.$pgettext("webhooks", "Send signed notifications when published content changes.")), 1),
				y.serverText ? (l(), n(b, {
					key: 0,
					type: "warning",
					variant: "tonal",
					class: "webhook-server mb-4"
				}, {
					default: p(() => [o(f(y.serverText), 1)]),
					_: 1
				})) : r("", !0),
				a("div", I, [
					a("div", L, [
						s(x, {
							"model-value": v.checked.size > 0,
							onClick: m(y.toggle, ["stop"]),
							"aria-label": e.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onClick",
							"aria-label"
						]),
						s(w, null, {
							activator: p(({ props: e, label: t }) => [s(S, c(e, {
								disabled: !v.checked.size,
								title: t,
								icon: _.mdiDotsVertical,
								variant: "text"
							}), null, 16, [
								"disabled",
								"title",
								"icon"
							])]),
							default: p(() => [s(C, null, {
								default: p(() => [s(S, {
									"prepend-icon": _.mdiDeleteForever,
									disabled: v.saving,
									variant: "text",
									onClick: h[0] ||= (e) => y.purge()
								}, {
									default: p(() => [o(f(e.$pgettext("webhooks", "Purge")) + " (" + f(v.checked.size) + ")", 1)]),
									_: 1
								}, 8, ["prepend-icon", "disabled"])]),
								_: 1
							})]),
							_: 1
						}),
						s(S, {
							title: e.$pgettext("webhooks", "Add webhook"),
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
					a("div", R, [s(T, {
						modelValue: v.term,
						"onUpdate:modelValue": h[1] ||= (e) => v.term = e,
						"prepend-inner-icon": _.mdiMagnify,
						label: e.$pgettext("webhooks", "Search for"),
						variant: "underlined",
						"hide-details": "",
						clearable: ""
					}, null, 8, [
						"modelValue",
						"prepend-inner-icon",
						"label"
					])]),
					a("div", z, [s(S, {
						title: e.$pgettext("webhooks", "Refresh"),
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
				s(O, {
					class: "items",
					role: "list"
				}, {
					default: p(() => [(l(!0), i(t, null, u(y.filtered, (t) => (l(), n(C, {
						key: t.id,
						class: "border-b rounded-0 pa-1",
						role: "listitem"
					}, {
						default: p(() => [a("div", B, [a("div", V, [s(x, {
							"model-value": v.checked.has(t.id),
							"onUpdate:modelValue": (e) => y.toggleCheck(t),
							"aria-label": e.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onUpdate:modelValue",
							"aria-label"
						]), s(w, null, {
							activator: p(({ props: e, label: t }) => [s(S, c({ ref_for: !0 }, e, {
								title: t,
								icon: _.mdiDotsVertical,
								variant: "text"
							}), null, 16, ["title", "icon"])]),
							default: p(() => [
								s(C, null, {
									default: p(() => [s(S, {
										"prepend-icon": _.mdiPencil,
										variant: "text",
										onClick: (e) => y.openEdit(t)
									}, {
										default: p(() => [o(f(e.$pgettext("webhooks", "Edit")), 1)]),
										_: 1
									}, 8, ["prepend-icon", "onClick"])]),
									_: 2
								}, 1024),
								s(C, null, {
									default: p(() => [s(S, {
										"prepend-icon": _.mdiKeyVariant,
										disabled: v.saving,
										class: "btn-rotate",
										variant: "text",
										onClick: (e) => y.rotate(t)
									}, {
										default: p(() => [o(f(e.$pgettext("webhooks", "Rotate")), 1)]),
										_: 1
									}, 8, [
										"prepend-icon",
										"disabled",
										"onClick"
									])]),
									_: 2
								}, 1024),
								s(E),
								s(C, null, {
									default: p(() => [s(S, {
										"prepend-icon": _.mdiDeleteForever,
										disabled: v.saving,
										variant: "text",
										onClick: (e) => y.purge(t)
									}, {
										default: p(() => [o(f(e.$pgettext("webhooks", "Purge")), 1)]),
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
						}, 1024)]), a("a", {
							href: "#",
							class: "item-content",
							onClick: m((e) => y.openEdit(t), ["prevent"])
						}, [a("div", U, [
							a("div", W, [a("span", G, f(t.name || t.endpoint), 1)]),
							t.name ? (l(), i("div", K, f(t.endpoint), 1)) : r("", !0),
							a("div", q, f(t.events.join(", ")), 1)
						]), a("div", J, [
							a("div", null, [s(D, {
								color: t.status ? "success" : void 0,
								size: "small"
							}, {
								default: p(() => [o(f(t.status ? e.$pgettext("webhooks", "Active") : e.$pgettext("webhooks", "Inactive")), 1)]),
								_: 2
							}, 1032, ["color"])]),
							a("div", Y, f(e.$pgettext("webhooks", "Last success")) + ": " + f(y.successText(t)), 1),
							a("div", X, f(e.$pgettext("webhooks", "Last error")) + ": " + f(y.errorText(t)), 1),
							t.paused_until ? (l(), i("div", Z, f(e.$pgettext("webhooks", "Paused until")) + ": " + f(y.dateText(t.paused_until)), 1)) : r("", !0)
						])], 8, H)])]),
						_: 2
					}, 1024))), 128))]),
					_: 1
				}),
				v.loading ? (l(), i("p", Q, [o(f(e.$pgettext("webhooks", "Loading")) + " ", 1), s(k, {
					width: "32",
					height: "32"
				})])) : y.filtered.length ? r("", !0) : (l(), i("p", $, f(v.items.length ? e.$pgettext("webhooks", "No entries found") : e.$pgettext("webhooks", "No webhooks configured.")), 1)),
				a("div", ee, [s(S, {
					title: e.$pgettext("webhooks", "Add webhook"),
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
	}), s(P, {
		modelValue: v.dialog,
		"onUpdate:modelValue": h[7] ||= (e) => v.dialog = e,
		title: v.secret ? e.$pgettext("webhooks", "Webhook secret") : v.selected ? e.$pgettext("webhooks", "Edit webhook") : e.$pgettext("webhooks", "Add webhook"),
		persistent: !!v.secret,
		"max-width": "640",
		onAfterLeave: h[8] ||= (e) => v.secret = ""
	}, {
		actions: p(({ close: a }) => [v.secret ? (l(), i(t, { key: 0 }, [s(S, {
			variant: "text",
			onClick: a
		}, {
			default: p(() => [o(f(e.$pgettext("webhooks", "Done")), 1)]),
			_: 1
		}, 8, ["onClick"]), s(S, {
			color: "primary",
			variant: "tonal",
			onClick: y.copySecret
		}, {
			default: p(() => [o(f(e.$pgettext("webhooks", "Copy secret")), 1)]),
			_: 1
		}, 8, ["onClick"])], 64)) : (l(), i(t, { key: 1 }, [
			v.selected ? (l(), n(S, {
				key: 0,
				class: "btn-test order-first",
				variant: "outlined",
				disabled: v.saving || !v.server.enabled,
				loading: v.testing,
				onClick: h[6] ||= (e) => y.ping(v.selected),
				active: ""
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Test")), 1)]),
				_: 1
			}, 8, ["disabled", "loading"])) : r("", !0),
			s(S, {
				variant: "text",
				onClick: a
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]),
			s(S, {
				color: "primary",
				variant: "tonal",
				disabled: v.testing || !v.form.events.length || !v.selected && !y.validUrl(v.form.url),
				loading: v.saving && !v.testing,
				onClick: y.save
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Save")), 1)]),
				_: 1
			}, 8, [
				"disabled",
				"loading",
				"onClick"
			])
		], 64))]),
		default: p(() => [v.secret ? (l(), i(t, { key: 0 }, [s(b, {
			type: "warning",
			variant: "tonal",
			class: "mb-4"
		}, {
			default: p(() => [o(f(e.$pgettext("webhooks", "Copy this secret now. It will not be shown again.")), 1)]),
			_: 1
		}), s(T, {
			"model-value": v.secret,
			class: "webhook-secret",
			variant: "underlined",
			readonly: ""
		}, null, 8, ["model-value"])], 64)) : (l(), i(t, { key: 1 }, [
			a("div", te, [a("div", ne, f(e.$pgettext("webhooks", "Active")), 1), s(M, {
				modelValue: v.form.status,
				"onUpdate:modelValue": h[2] ||= (e) => v.form.status = e,
				"aria-label": e.$pgettext("webhooks", "Active"),
				hint: e.$pgettext("webhooks", "Inactive webhooks receive no events and their queued deliveries are dropped"),
				color: "primary",
				"hide-details": "auto",
				inset: ""
			}, null, 8, [
				"modelValue",
				"aria-label",
				"hint"
			])]),
			v.selected ? (l(), i(t, { key: 1 }, [a("p", re, f(v.selected.endpoint), 1), y.undecryptable(v.selected) ? (l(), n(b, {
				key: 0,
				type: "warning",
				variant: "tonal",
				class: "webhook-undecryptable mb-4"
			}, {
				default: p(() => [o(f(y.reasonText("invalid_encryption")), 1)]),
				_: 1
			})) : r("", !0)], 64)) : (l(), n(T, {
				key: 0,
				modelValue: v.form.url,
				"onUpdate:modelValue": h[3] ||= (e) => v.form.url = e,
				label: e.$pgettext("webhooks", "HTTPS endpoint URL"),
				hint: e.$pgettext("webhooks", "Address receiving the signed events, it cannot be changed later"),
				rules: [(t) => y.validUrl(t) || e.$pgettext("webhooks", "Not a valid URL")],
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
			s(N, {
				modelValue: v.form.events,
				"onUpdate:modelValue": h[4] ||= (e) => v.form.events = e,
				items: v.names,
				label: e.$pgettext("webhooks", "Events"),
				hint: e.$pgettext("webhooks", "Events sent to the webhook, queued deliveries of removed events are cancelled"),
				variant: "underlined",
				multiple: "",
				chips: ""
			}, null, 8, [
				"modelValue",
				"items",
				"label",
				"hint"
			]),
			s(T, {
				modelValue: v.form.name,
				"onUpdate:modelValue": h[5] ||= (e) => v.form.name = e,
				label: e.$pgettext("webhooks", "Name"),
				hint: e.$pgettext("webhooks", "Optional name to tell webhooks with the same endpoint apart, visible to all webhook editors"),
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
		"modelValue",
		"title",
		"persistent"
	])], 64);
}
var ae = /*#__PURE__*/ T(P, [["render", ie], ["__scopeId", "data-v-593a014e"]]);
//#endregion
export { ae as default };
