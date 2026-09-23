import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, mergeProps as c, openBlock as l, renderList as ee, resolveComponent as u, toDisplayString as d, withCtx as f, withModifiers as p } from "vue";
//#region node_modules/@mdi/js/mdi.js
var m = "M12 2C6.5 2 2 6.5 2 12S6.5 22 12 22 22 17.5 22 12 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20M16.59 7.58L10 14.17L7.41 11.59L6 13L10 17L18 9L16.59 7.58Z", h = "M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22C17.53,22 22,17.53 22,12C22,6.47 17.53,2 12,2M14.59,8L12,10.59L9.41,8L8,9.41L10.59,12L8,14.59L9.41,16L12,13.41L14.59,16L16,14.59L13.41,12L16,9.41L14.59,8Z", g = "M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M8.46,11.88L9.87,10.47L12,12.59L14.12,10.47L15.53,11.88L13.41,14L15.53,16.12L14.12,17.53L12,15.41L9.88,17.53L8.47,16.12L10.59,14L8.46,11.88M15.5,4L14.5,3H9.5L8.5,4H5V6H19V4H15.5Z", _ = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", v = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", y = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", b = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", x = "M14 10H3V12H14V10M14 6H3V8H14V6M3 16H10V14H3V16M21.5 11.5L23 13L16 20L11.5 15.5L13 14L16 17L21.5 11.5Z", S = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", C = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", w = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, T = e`
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
`, E = e`
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
  ${T}
`, D = e`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${T}
`, O = e`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${T}
`, k = e`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${T}
`, A = e`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
`, j = e`
  mutation PurgeWebhook($id: [ID!]!) {
    purgeWebhook(id: $id)
  }
`, M = {
	name: "WebhookList",
	inject: {
		apollo: {},
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
			url: "",
			name: "",
			events: [],
			status: !1,
			secret: "",
			server: {
				enabled: !0,
				blocked: null
			}
		};
	},
	setup() {
		return {
			mdiDeleteForever: g,
			mdiDotsVertical: _,
			mdiKeyVariant: v,
			mdiMagnify: y,
			mdiPencil: b,
			mdiPlus: S,
			mdiRefresh: C
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
				items: [
					{
						title: this.$pgettext("webhooks", "All"),
						icon: x,
						value: { status: null }
					},
					{
						title: this.$pgettext("webhooks", "Active"),
						icon: m,
						value: { status: !0 }
					},
					{
						title: this.$pgettext("webhooks", "Inactive"),
						icon: h,
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
					this.messages.add(t + ":\n" + e, "error");
				} finally {
					this.saving = !1;
				}
			}
		},
		dateText(e) {
			return new Date(e).toLocaleString(this.$vuetify.locale.current);
		},
		async load() {
			this.loading = !0;
			try {
				let { data: e } = await this.apollo.query({
					query: E,
					fetchPolicy: "network-only"
				});
				this.items = e.cmsWebhooks, this.checked = /* @__PURE__ */ new Set(), this.names = e.cmsWebhookEvents, this.server = e.cmsWebhookServer;
			} catch (e) {
				this.messages.add(this.$pgettext("webhooks", "Error fetching webhooks") + ":\n" + e, "error");
			} finally {
				this.loading = !1;
			}
		},
		openAdd() {
			this.selected = null, this.url = "", this.name = "", this.events = [], this.status = !1, this.secret = "", this.dialog = !0;
		},
		openEdit(e) {
			this.selected = e, this.name = e.name, this.events = [...e.events], this.status = e.status, this.secret = "", this.dialog = !0;
		},
		async save() {
			this.events.length && (this.selected || this.validUrl(this.url)) && await this.change(async () => {
				if (this.selected) {
					let { data: e } = await this.apollo.mutate({
						mutation: O,
						variables: {
							id: this.selected.id,
							input: {
								name: this.name.trim(),
								events: this.events,
								status: this.status
							}
						}
					});
					this.put(e.saveWebhook), this.dialog = !1;
				} else {
					let { data: e } = await this.apollo.mutate({
						mutation: D,
						variables: { input: {
							url: this.url.trim(),
							name: this.name.trim(),
							events: this.events,
							status: this.status
						} }
					});
					this.put(e.addWebhook.webhook), this.secret = e.addWebhook.secret;
				}
			}, this.$pgettext("webhooks", "Error saving webhook"));
		},
		async rotate(e) {
			let t = this.$pgettext("webhooks", "Rotate the secret of this webhook? Receivers must be updated with the new secret.");
			!this.saving && window.confirm(`${t}\n\n${this.label(e)}`) && await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: k,
					variables: { id: e.id }
				});
				this.put(t.rotateWebhook.webhook), this.secret = t.rotateWebhook.secret, this.dialog = !0;
			}, this.$pgettext("webhooks", "Error rotating webhook secret"));
		},
		async ping(e) {
			this.testing = !0, await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: A,
					variables: { id: e.id }
				}), n = t.pingWebhook;
				n.success ? (this.items = this.items.map((t) => t.id === e.id ? {
					...t,
					paused_until: null
				} : t), this.messages.add(this.$pgettext("webhooks", "Test event delivered") + ` (${n.status})`, "success")) : this.messages.add(this.$pgettext("webhooks", "Test event failed") + ": " + this.reasonText(n.reason, n.status), "error");
			}, this.$pgettext("webhooks", "Test event failed")), this.testing = !1;
		},
		async purge(e = null) {
			let t = e ? [e.id] : [...this.checked], n = e ? `${this.$pgettext("webhooks", "Purge this webhook?")}\n\n${this.label(e)}` : `${this.$pgettext("webhooks", "Purge")} (${t.length})?`;
			!this.saving && t.length && window.confirm(n) && await this.change(async () => {
				for (let e = 0; e < t.length; e += 100) {
					let n = new Set(t.slice(e, e + 100));
					await this.apollo.mutate({
						mutation: j,
						variables: { id: [...n] }
					}), this.items = this.items.filter((e) => !n.has(e.id)), this.checked = new Set([...this.checked].filter((e) => !n.has(e)));
				}
			}, this.$pgettext("webhooks", "Error purging webhook"));
		},
		async copySecret() {
			try {
				await navigator.clipboard.writeText(this.secret), this.messages.add(this.$pgettext("webhooks", "Secret copied"), "success");
			} catch {
				this.messages.add(this.$pgettext("webhooks", "Unable to copy secret"), "error");
			}
		},
		errorText(e) {
			let t = e.last_error;
			if (!t) return this.$pgettext("webhooks", "None");
			let n = this.reasonText(t.reason, t.status);
			return t.at ? `${n} · ${this.dateText(t.at)}` : n;
		},
		label(e) {
			return e.name ? `${e.name} · ${e.endpoint}` : e.endpoint;
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
		successText(e) {
			return e.last_success_at ? this.dateText(e.last_success_at) : this.$pgettext("webhooks", "None");
		},
		undecryptable(e) {
			return e?.last_error?.reason === "invalid_encryption";
		},
		toggle() {
			this.checked = this.checked.size ? /* @__PURE__ */ new Set() : new Set(this.filtered.map((e) => e.id));
		},
		toggleCheck(e) {
			let t = new Set(this.checked);
			t.has(e.id) ? t.delete(e.id) : t.add(e.id), this.checked = t;
		},
		put(e) {
			let t = new Set(this.checked);
			t.delete(e.id), this.items = this.items.some((t) => t.id === e.id) ? this.items.map((t) => t.id === e.id ? e : t) : [e, ...this.items], this.checked = t;
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
}, N = { class: "text-medium-emphasis mb-4" }, P = { class: "header" }, F = { class: "bulk" }, I = { class: "search" }, L = { class: "layout" }, R = { class: "d-flex align-center w-100" }, z = { class: "d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2" }, B = ["onClick"], V = { class: "item-text" }, H = { class: "item-head" }, U = { class: "item-title" }, W = {
	key: 0,
	class: "item-subtitle item-endpoint"
}, G = { class: "item-subtitle" }, K = { class: "item-aux text-end" }, q = { class: "item-subtitle" }, J = { class: "item-subtitle" }, Y = {
	key: 0,
	class: "item-subtitle webhook-paused text-warning"
}, X = {
	key: 1,
	class: "loading"
}, Z = {
	key: 2,
	class: "notfound"
}, Q = { class: "btn-group" }, te = { class: "webhook-status" }, ne = { class: "webhook-status-label label d-flex align-center font-weight-bold mb-1" }, re = { class: "webhook-current on-surface text-break mt-4 mb-4" };
function $(e, m, h, g, _, v) {
	let y = u("v-alert"), b = u("v-checkbox-btn"), x = u("v-btn"), S = u("v-list-item"), C = u("CmsActionMenu"), w = u("v-text-field"), T = u("v-divider"), E = u("v-chip"), D = u("v-list"), O = u("CmsLoadingSpinner"), k = u("v-sheet"), A = u("v-container"), j = u("v-switch"), M = u("v-select"), $ = u("CmsDialog");
	return l(), i(t, null, [s(A, { class: "webhook-list" }, {
		default: f(() => [s(k, { class: "box scroll" }, {
			default: f(() => [
				a("p", N, d(e.$pgettext("webhooks", "Send signed notifications when published content changes.")), 1),
				v.serverText ? (l(), n(y, {
					key: 0,
					type: "warning",
					variant: "tonal",
					class: "webhook-server mb-4"
				}, {
					default: f(() => [o(d(v.serverText), 1)]),
					_: 1
				})) : r("", !0),
				a("div", P, [
					a("div", F, [
						s(b, {
							"model-value": _.checked.size > 0,
							onClick: p(v.toggle, ["stop"]),
							"aria-label": e.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onClick",
							"aria-label"
						]),
						s(C, null, {
							activator: f(({ props: e, label: t }) => [s(x, c(e, {
								disabled: !_.checked.size,
								title: t,
								icon: g.mdiDotsVertical,
								variant: "text"
							}), null, 16, [
								"disabled",
								"title",
								"icon"
							])]),
							default: f(() => [s(S, null, {
								default: f(() => [s(x, {
									"prepend-icon": g.mdiDeleteForever,
									disabled: _.saving,
									variant: "text",
									onClick: m[0] ||= (e) => v.purge()
								}, {
									default: f(() => [o(d(e.$pgettext("webhooks", "Purge")) + " (" + d(_.checked.size) + ")", 1)]),
									_: 1
								}, 8, ["prepend-icon", "disabled"])]),
								_: 1
							})]),
							_: 1
						}),
						s(x, {
							title: e.$pgettext("webhooks", "Add webhook"),
							disabled: _.loading,
							icon: g.mdiPlus,
							class: "btn-add",
							color: "primary",
							variant: "tonal",
							onClick: v.openAdd
						}, null, 8, [
							"title",
							"disabled",
							"icon",
							"onClick"
						])
					]),
					a("div", I, [s(w, {
						modelValue: _.term,
						"onUpdate:modelValue": m[1] ||= (e) => _.term = e,
						"prepend-inner-icon": g.mdiMagnify,
						label: e.$pgettext("webhooks", "Search for"),
						variant: "underlined",
						"hide-details": "",
						clearable: ""
					}, null, 8, [
						"modelValue",
						"prepend-inner-icon",
						"label"
					])]),
					a("div", L, [s(x, {
						title: e.$pgettext("webhooks", "Refresh"),
						icon: g.mdiRefresh,
						loading: _.loading,
						class: "btn-reload",
						variant: "text",
						onClick: v.load
					}, null, 8, [
						"title",
						"icon",
						"loading",
						"onClick"
					])])
				]),
				s(D, {
					class: "items",
					role: "list"
				}, {
					default: f(() => [(l(!0), i(t, null, ee(v.filtered, (t) => (l(), n(S, {
						key: t.id,
						class: "border-b rounded-0 pa-1",
						role: "listitem"
					}, {
						default: f(() => [a("div", R, [a("div", z, [s(b, {
							"model-value": _.checked.has(t.id),
							"onUpdate:modelValue": (e) => v.toggleCheck(t),
							"aria-label": e.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onUpdate:modelValue",
							"aria-label"
						]), s(C, null, {
							activator: f(({ props: e, label: t }) => [s(x, c({ ref_for: !0 }, e, {
								title: t,
								icon: g.mdiDotsVertical,
								variant: "text"
							}), null, 16, ["title", "icon"])]),
							default: f(() => [
								s(S, null, {
									default: f(() => [s(x, {
										"prepend-icon": g.mdiPencil,
										variant: "text",
										onClick: (e) => v.openEdit(t)
									}, {
										default: f(() => [o(d(e.$pgettext("webhooks", "Edit")), 1)]),
										_: 1
									}, 8, ["prepend-icon", "onClick"])]),
									_: 2
								}, 1024),
								s(S, null, {
									default: f(() => [s(x, {
										"prepend-icon": g.mdiKeyVariant,
										disabled: _.saving,
										class: "btn-rotate",
										variant: "text",
										onClick: (e) => v.rotate(t)
									}, {
										default: f(() => [o(d(e.$pgettext("webhooks", "Rotate")), 1)]),
										_: 1
									}, 8, [
										"prepend-icon",
										"disabled",
										"onClick"
									])]),
									_: 2
								}, 1024),
								s(T),
								s(S, null, {
									default: f(() => [s(x, {
										"prepend-icon": g.mdiDeleteForever,
										disabled: _.saving,
										variant: "text",
										onClick: (e) => v.purge(t)
									}, {
										default: f(() => [o(d(e.$pgettext("webhooks", "Purge")), 1)]),
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
							onClick: p((e) => v.openEdit(t), ["prevent"])
						}, [a("div", V, [
							a("div", H, [a("span", U, d(t.name || t.endpoint), 1)]),
							t.name ? (l(), i("div", W, d(t.endpoint), 1)) : r("", !0),
							a("div", G, d(t.events.join(", ")), 1)
						]), a("div", K, [
							a("div", null, [s(E, {
								color: t.status ? "success" : void 0,
								size: "small"
							}, {
								default: f(() => [o(d(t.status ? e.$pgettext("webhooks", "Active") : e.$pgettext("webhooks", "Inactive")), 1)]),
								_: 2
							}, 1032, ["color"])]),
							a("div", q, d(e.$pgettext("webhooks", "Last success")) + ": " + d(v.successText(t)), 1),
							a("div", J, d(e.$pgettext("webhooks", "Last error")) + ": " + d(v.errorText(t)), 1),
							t.paused_until ? (l(), i("div", Y, d(e.$pgettext("webhooks", "Paused until")) + ": " + d(v.dateText(t.paused_until)), 1)) : r("", !0)
						])], 8, B)])]),
						_: 2
					}, 1024))), 128))]),
					_: 1
				}),
				_.loading ? (l(), i("p", X, [o(d(e.$pgettext("webhooks", "Loading")) + " ", 1), s(O, {
					width: "32",
					height: "32"
				})])) : v.filtered.length ? r("", !0) : (l(), i("p", Z, d(_.items.length ? e.$pgettext("webhooks", "No entries found") : e.$pgettext("webhooks", "No webhooks configured.")), 1)),
				a("div", Q, [s(x, {
					title: e.$pgettext("webhooks", "Add webhook"),
					disabled: _.loading,
					icon: g.mdiPlus,
					class: "btn-add",
					color: "primary",
					variant: "tonal",
					onClick: v.openAdd
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
	}), s($, {
		modelValue: _.dialog,
		"onUpdate:modelValue": m[7] ||= (e) => _.dialog = e,
		title: _.secret ? e.$pgettext("webhooks", "Webhook secret") : _.selected ? e.$pgettext("webhooks", "Edit webhook") : e.$pgettext("webhooks", "Add webhook"),
		persistent: !!_.secret,
		"max-width": "640",
		onAfterLeave: m[8] ||= (e) => _.secret = ""
	}, {
		actions: f(({ close: a }) => [_.secret ? (l(), i(t, { key: 0 }, [s(x, {
			variant: "outlined",
			onClick: a
		}, {
			default: f(() => [o(d(e.$pgettext("webhooks", "Done")), 1)]),
			_: 1
		}, 8, ["onClick"]), s(x, {
			color: "primary",
			variant: "tonal",
			onClick: v.copySecret,
			active: ""
		}, {
			default: f(() => [o(d(e.$pgettext("webhooks", "Copy secret")), 1)]),
			_: 1
		}, 8, ["onClick"])], 64)) : (l(), i(t, { key: 1 }, [
			_.selected ? (l(), n(x, {
				key: 0,
				class: "btn-test order-first",
				color: "warning",
				variant: "tonal",
				disabled: _.saving || !_.server.enabled,
				loading: _.testing,
				onClick: m[6] ||= (e) => v.ping(_.selected),
				active: ""
			}, {
				default: f(() => [o(d(e.$pgettext("webhooks", "Test")), 1)]),
				_: 1
			}, 8, ["disabled", "loading"])) : r("", !0),
			s(x, {
				variant: "outlined",
				onClick: a
			}, {
				default: f(() => [o(d(e.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]),
			s(x, {
				color: "primary",
				variant: "tonal",
				disabled: _.testing || !_.events.length || !_.selected && !v.validUrl(_.url),
				loading: _.saving && !_.testing,
				onClick: v.save,
				active: ""
			}, {
				default: f(() => [o(d(e.$pgettext("webhooks", "Save")), 1)]),
				_: 1
			}, 8, [
				"disabled",
				"loading",
				"onClick"
			])
		], 64))]),
		default: f(() => [_.secret ? (l(), i(t, { key: 0 }, [s(y, {
			type: "warning",
			variant: "tonal",
			class: "mb-4"
		}, {
			default: f(() => [o(d(e.$pgettext("webhooks", "Copy this secret now. It will not be shown again.")), 1)]),
			_: 1
		}), s(w, {
			"model-value": _.secret,
			class: "webhook-secret",
			variant: "underlined",
			readonly: ""
		}, null, 8, ["model-value"])], 64)) : (l(), i(t, { key: 1 }, [
			a("div", te, [a("div", ne, d(e.$pgettext("webhooks", "Active")), 1), s(j, {
				modelValue: _.status,
				"onUpdate:modelValue": m[2] ||= (e) => _.status = e,
				"aria-label": e.$pgettext("webhooks", "Active"),
				color: "primary",
				"hide-details": "",
				inset: ""
			}, null, 8, ["modelValue", "aria-label"])]),
			_.selected ? (l(), i(t, { key: 1 }, [a("p", re, d(_.selected.endpoint), 1), v.undecryptable(_.selected) ? (l(), n(y, {
				key: 0,
				type: "warning",
				variant: "tonal",
				class: "webhook-undecryptable mb-4"
			}, {
				default: f(() => [o(d(v.reasonText("invalid_encryption")), 1)]),
				_: 1
			})) : r("", !0)], 64)) : (l(), n(w, {
				key: 0,
				modelValue: _.url,
				"onUpdate:modelValue": m[3] ||= (e) => _.url = e,
				label: e.$pgettext("webhooks", "HTTPS endpoint URL"),
				rules: [(t) => v.validUrl(t) || e.$pgettext("webhooks", "Not a valid URL")],
				"validate-on": "invalid-input",
				variant: "underlined",
				maxlength: "500",
				autofocus: ""
			}, null, 8, [
				"modelValue",
				"label",
				"rules"
			])),
			s(M, {
				modelValue: _.events,
				"onUpdate:modelValue": m[4] ||= (e) => _.events = e,
				items: _.names,
				label: e.$pgettext("webhooks", "Events"),
				variant: "underlined",
				multiple: "",
				chips: ""
			}, null, 8, [
				"modelValue",
				"items",
				"label"
			]),
			s(w, {
				modelValue: _.name,
				"onUpdate:modelValue": m[5] ||= (e) => _.name = e,
				label: e.$pgettext("webhooks", "Name"),
				class: "webhook-name",
				variant: "underlined",
				maxlength: "100"
			}, null, 8, ["modelValue", "label"])
		], 64))]),
		_: 1
	}, 8, [
		"modelValue",
		"title",
		"persistent"
	])], 64);
}
var ie = /*#__PURE__*/ w(M, [["render", $]]);
//#endregion
export { ie as default };
