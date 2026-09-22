import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, mergeProps as c, openBlock as l, renderList as ee, resolveComponent as u, toDisplayString as d, withCtx as f, withModifiers as p } from "vue";
//#region node_modules/@mdi/js/mdi.js
var m = "M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z", te = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", h = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", g = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", _ = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", v = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", y = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", b = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, x = e`
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
`, S = e`
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
  ${x}
`, C = e`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${x}
`, w = e`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${x}
`, T = e`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${x}
`, E = e`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
`, D = e`
  mutation DropWebhook($id: [ID!]!) {
    dropWebhook(id: $id)
  }
`, O = {
	name: "WebhookList",
	inject: ["apollo", "messages"],
	data: () => ({
		dialog: !1,
		loading: !0,
		saving: !1,
		testing: !1,
		items: [],
		checked: /* @__PURE__ */ new Set(),
		names: [],
		selected: null,
		term: "",
		statusFilter: null,
		url: "",
		name: "",
		events: [],
		status: !1,
		secret: "",
		server: {
			enabled: !0,
			blocked: null
		}
	}),
	setup() {
		return {
			mdiDelete: m,
			mdiDotsVertical: te,
			mdiKeyVariant: h,
			mdiMagnify: g,
			mdiPencil: _,
			mdiPlus: v,
			mdiRefresh: y
		};
	},
	computed: {
		serverText() {
			return this.server.enabled ? this.server.blocked ? this.$pgettext("webhooks", "All deliveries are blocked by the server configuration") : "" : this.$pgettext("webhooks", "Webhooks are disabled by the server configuration, no events are sent");
		},
		filtered() {
			let e = (this.term ?? "").trim().toLocaleLowerCase();
			return this.items.filter((t) => this.statusFilter !== null && t.status !== this.statusFilter ? !1 : !e || t.name.toLocaleLowerCase().includes(e) || t.endpoint.toLocaleLowerCase().includes(e) || t.events.some((t) => t.toLocaleLowerCase().includes(e)));
		},
		statusItems() {
			return [
				{
					title: this.$pgettext("webhooks", "All"),
					value: null
				},
				{
					title: this.$pgettext("webhooks", "Active"),
					value: !0
				},
				{
					title: this.$pgettext("webhooks", "Inactive"),
					value: !1
				}
			];
		}
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
					query: S,
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
						mutation: w,
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
						mutation: C,
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
					mutation: T,
					variables: { id: e.id }
				});
				this.put(t.rotateWebhook.webhook), this.secret = t.rotateWebhook.secret, this.dialog = !0;
			}, this.$pgettext("webhooks", "Error rotating webhook secret"));
		},
		async ping(e) {
			this.testing = !0, await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: E,
					variables: { id: e.id }
				}), n = t.pingWebhook;
				n.success ? (this.items = this.items.map((t) => t.id === e.id ? {
					...t,
					paused_until: null
				} : t), this.messages.add(this.$pgettext("webhooks", "Test event delivered") + ` (${n.status})`, "success")) : this.messages.add(this.$pgettext("webhooks", "Test event failed") + ": " + this.reasonText(n.reason, n.status), "error");
			}, this.$pgettext("webhooks", "Test event failed")), this.testing = !1;
		},
		async remove(e = null) {
			let t = e ? [e.id] : [...this.checked], n = e ? `${this.$pgettext("webhooks", "Delete this webhook?")}\n\n${this.label(e)}` : `${this.$pgettext("webhooks", "Delete")} (${t.length})?`;
			!this.saving && t.length && window.confirm(n) && await this.change(async () => {
				for (let e = 0; e < t.length; e += 100) {
					let n = new Set(t.slice(e, e + 100));
					await this.apollo.mutate({
						mutation: D,
						variables: { id: [...n] }
					}), this.items = this.items.filter((e) => !n.has(e.id)), this.checked = new Set([...this.checked].filter((e) => !n.has(e)));
				}
			}, this.$pgettext("webhooks", "Error deleting webhook"));
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
	},
	watch: {
		statusFilter() {
			this.checked = /* @__PURE__ */ new Set();
		},
		term() {
			this.checked = /* @__PURE__ */ new Set();
		}
	}
}, k = { class: "text-medium-emphasis mb-4" }, A = { class: "header" }, j = { class: "bulk" }, M = { class: "search" }, N = { class: "layout" }, P = { class: "d-flex align-center w-100" }, F = { class: "d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2" }, I = ["onClick"], L = { class: "item-text" }, R = { class: "item-head" }, z = { class: "item-title" }, B = {
	key: 0,
	class: "item-subtitle item-endpoint"
}, V = { class: "item-subtitle" }, H = { class: "item-aux text-end" }, U = { class: "item-subtitle" }, W = { class: "item-subtitle" }, G = {
	key: 0,
	class: "item-subtitle webhook-paused text-warning"
}, K = {
	key: 1,
	class: "loading"
}, q = {
	key: 2,
	class: "notfound"
}, J = { class: "btn-group" }, Y = { class: "webhook-status" }, X = { class: "webhook-status-label label d-flex align-center font-weight-bold mb-1" }, Z = { class: "webhook-current on-surface text-break mt-4 mb-4" };
function Q(e, m, te, h, g, _) {
	let v = u("v-alert"), y = u("v-checkbox-btn"), b = u("v-btn"), x = u("v-list-item"), S = u("CmsActionMenu"), C = u("v-text-field"), w = u("v-select"), T = u("v-divider"), E = u("v-chip"), D = u("v-list"), O = u("CmsLoadingSpinner"), Q = u("v-sheet"), $ = u("v-container"), ne = u("v-switch"), re = u("CmsDialog");
	return l(), i(t, null, [s($, { class: "webhook-list" }, {
		default: f(() => [s(Q, { class: "box scroll" }, {
			default: f(() => [
				a("p", k, d(e.$pgettext("webhooks", "Send signed notifications when published content changes.")), 1),
				_.serverText ? (l(), n(v, {
					key: 0,
					type: "warning",
					variant: "tonal",
					class: "webhook-server mb-4"
				}, {
					default: f(() => [o(d(_.serverText), 1)]),
					_: 1
				})) : r("", !0),
				a("div", A, [
					a("div", j, [
						s(y, {
							"model-value": e.checked.size > 0,
							onClick: p(_.toggle, ["stop"]),
							"aria-label": e.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onClick",
							"aria-label"
						]),
						s(S, null, {
							activator: f(({ props: t, label: n }) => [s(b, c(t, {
								disabled: !e.checked.size,
								title: n,
								icon: h.mdiDotsVertical,
								variant: "text"
							}), null, 16, [
								"disabled",
								"title",
								"icon"
							])]),
							default: f(() => [s(x, null, {
								default: f(() => [s(b, {
									"prepend-icon": h.mdiDelete,
									disabled: e.saving,
									variant: "text",
									onClick: m[0] ||= (e) => _.remove()
								}, {
									default: f(() => [o(d(e.$pgettext("webhooks", "Delete")) + " (" + d(e.checked.size) + ")", 1)]),
									_: 1
								}, 8, ["prepend-icon", "disabled"])]),
								_: 1
							})]),
							_: 1
						}),
						s(b, {
							title: e.$pgettext("webhooks", "Add webhook"),
							disabled: e.loading,
							icon: h.mdiPlus,
							class: "btn-add",
							color: "primary",
							variant: "tonal",
							onClick: _.openAdd
						}, null, 8, [
							"title",
							"disabled",
							"icon",
							"onClick"
						])
					]),
					a("div", M, [s(C, {
						modelValue: e.term,
						"onUpdate:modelValue": m[1] ||= (t) => e.term = t,
						"prepend-inner-icon": h.mdiMagnify,
						label: e.$pgettext("webhooks", "Search for"),
						variant: "underlined",
						"hide-details": "",
						clearable: ""
					}, null, 8, [
						"modelValue",
						"prepend-inner-icon",
						"label"
					]), s(w, {
						modelValue: e.statusFilter,
						"onUpdate:modelValue": m[2] ||= (t) => e.statusFilter = t,
						items: _.statusItems,
						label: e.$pgettext("webhooks", "Status"),
						variant: "underlined",
						"hide-details": ""
					}, null, 8, [
						"modelValue",
						"items",
						"label"
					])]),
					a("div", N, [s(b, {
						title: e.$pgettext("webhooks", "Refresh"),
						icon: h.mdiRefresh,
						loading: e.loading,
						class: "btn-reload",
						variant: "text",
						onClick: _.load
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
					default: f(() => [(l(!0), i(t, null, ee(_.filtered, (t) => (l(), n(x, {
						key: t.id,
						class: "border-b rounded-0 pa-1",
						role: "listitem"
					}, {
						default: f(() => [a("div", P, [a("div", F, [s(y, {
							"model-value": e.checked.has(t.id),
							"onUpdate:modelValue": (e) => _.toggleCheck(t),
							"aria-label": e.$pgettext("webhooks", "Toggle selection")
						}, null, 8, [
							"model-value",
							"onUpdate:modelValue",
							"aria-label"
						]), s(S, null, {
							activator: f(({ props: e, label: t }) => [s(b, c({ ref_for: !0 }, e, {
								title: t,
								icon: h.mdiDotsVertical,
								variant: "text"
							}), null, 16, ["title", "icon"])]),
							default: f(() => [
								s(x, null, {
									default: f(() => [s(b, {
										"prepend-icon": h.mdiPencil,
										variant: "text",
										onClick: (e) => _.openEdit(t)
									}, {
										default: f(() => [o(d(e.$pgettext("webhooks", "Edit")), 1)]),
										_: 1
									}, 8, ["prepend-icon", "onClick"])]),
									_: 2
								}, 1024),
								s(x, null, {
									default: f(() => [s(b, {
										"prepend-icon": h.mdiKeyVariant,
										disabled: e.saving,
										class: "btn-rotate",
										variant: "text",
										onClick: (e) => _.rotate(t)
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
								s(x, null, {
									default: f(() => [s(b, {
										"prepend-icon": h.mdiDelete,
										disabled: e.saving,
										variant: "text",
										onClick: (e) => _.remove(t)
									}, {
										default: f(() => [o(d(e.$pgettext("webhooks", "Delete")), 1)]),
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
							onClick: p((e) => _.openEdit(t), ["prevent"])
						}, [a("div", L, [
							a("div", R, [a("span", z, d(t.name || t.endpoint), 1)]),
							t.name ? (l(), i("div", B, d(t.endpoint), 1)) : r("", !0),
							a("div", V, d(t.events.join(", ")), 1)
						]), a("div", H, [
							a("div", null, [s(E, {
								color: t.status ? "success" : void 0,
								size: "small"
							}, {
								default: f(() => [o(d(t.status ? e.$pgettext("webhooks", "Active") : e.$pgettext("webhooks", "Inactive")), 1)]),
								_: 2
							}, 1032, ["color"])]),
							a("div", U, d(e.$pgettext("webhooks", "Last success")) + ": " + d(_.successText(t)), 1),
							a("div", W, d(e.$pgettext("webhooks", "Last error")) + ": " + d(_.errorText(t)), 1),
							t.paused_until ? (l(), i("div", G, d(e.$pgettext("webhooks", "Paused until")) + ": " + d(_.dateText(t.paused_until)), 1)) : r("", !0)
						])], 8, I)])]),
						_: 2
					}, 1024))), 128))]),
					_: 1
				}),
				e.loading ? (l(), i("p", K, [o(d(e.$pgettext("webhooks", "Loading")) + " ", 1), s(O, {
					width: "32",
					height: "32"
				})])) : _.filtered.length ? r("", !0) : (l(), i("p", q, d(e.items.length ? e.$pgettext("webhooks", "No entries found") : e.$pgettext("webhooks", "No webhooks configured.")), 1)),
				a("div", J, [s(b, {
					title: e.$pgettext("webhooks", "Add webhook"),
					disabled: e.loading,
					icon: h.mdiPlus,
					class: "btn-add",
					color: "primary",
					variant: "tonal",
					onClick: _.openAdd
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
	}), s(re, {
		modelValue: e.dialog,
		"onUpdate:modelValue": m[8] ||= (t) => e.dialog = t,
		title: e.secret ? e.$pgettext("webhooks", "Webhook secret") : e.selected ? e.$pgettext("webhooks", "Edit webhook") : e.$pgettext("webhooks", "Add webhook"),
		persistent: !!e.secret,
		"max-width": "640",
		onAfterLeave: m[9] ||= (t) => e.secret = ""
	}, {
		actions: f(({ close: a }) => [e.secret ? (l(), i(t, { key: 0 }, [s(b, {
			variant: "outlined",
			onClick: a
		}, {
			default: f(() => [o(d(e.$pgettext("webhooks", "Done")), 1)]),
			_: 1
		}, 8, ["onClick"]), s(b, {
			color: "primary",
			variant: "tonal",
			onClick: _.copySecret,
			active: ""
		}, {
			default: f(() => [o(d(e.$pgettext("webhooks", "Copy secret")), 1)]),
			_: 1
		}, 8, ["onClick"])], 64)) : (l(), i(t, { key: 1 }, [
			e.selected ? (l(), n(b, {
				key: 0,
				class: "btn-test order-first",
				color: "warning",
				variant: "tonal",
				disabled: e.saving || !e.server.enabled,
				loading: e.testing,
				onClick: m[7] ||= (t) => _.ping(e.selected),
				active: ""
			}, {
				default: f(() => [o(d(e.$pgettext("webhooks", "Test")), 1)]),
				_: 1
			}, 8, ["disabled", "loading"])) : r("", !0),
			s(b, {
				variant: "outlined",
				onClick: a
			}, {
				default: f(() => [o(d(e.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]),
			s(b, {
				color: "primary",
				variant: "tonal",
				disabled: e.testing || !e.events.length || !e.selected && !_.validUrl(e.url),
				loading: e.saving && !e.testing,
				onClick: _.save,
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
		default: f(() => [e.secret ? (l(), i(t, { key: 0 }, [s(v, {
			type: "warning",
			variant: "tonal",
			class: "mb-4"
		}, {
			default: f(() => [o(d(e.$pgettext("webhooks", "Copy this secret now. It will not be shown again.")), 1)]),
			_: 1
		}), s(C, {
			"model-value": e.secret,
			class: "webhook-secret",
			variant: "underlined",
			readonly: ""
		}, null, 8, ["model-value"])], 64)) : (l(), i(t, { key: 1 }, [
			a("div", Y, [a("div", X, d(e.$pgettext("webhooks", "Active")), 1), s(ne, {
				modelValue: e.status,
				"onUpdate:modelValue": m[3] ||= (t) => e.status = t,
				"aria-label": e.$pgettext("webhooks", "Active"),
				color: "primary",
				"hide-details": "",
				inset: ""
			}, null, 8, ["modelValue", "aria-label"])]),
			e.selected ? (l(), i(t, { key: 1 }, [a("p", Z, d(e.selected.endpoint), 1), _.undecryptable(e.selected) ? (l(), n(v, {
				key: 0,
				type: "warning",
				variant: "tonal",
				class: "webhook-undecryptable mb-4"
			}, {
				default: f(() => [o(d(_.reasonText("invalid_encryption")), 1)]),
				_: 1
			})) : r("", !0)], 64)) : (l(), n(C, {
				key: 0,
				modelValue: e.url,
				"onUpdate:modelValue": m[4] ||= (t) => e.url = t,
				label: e.$pgettext("webhooks", "HTTPS endpoint URL"),
				rules: [(t) => _.validUrl(t) || e.$pgettext("webhooks", "Not a valid URL")],
				"validate-on": "invalid-input",
				variant: "underlined",
				maxlength: "500",
				autofocus: ""
			}, null, 8, [
				"modelValue",
				"label",
				"rules"
			])),
			s(w, {
				modelValue: e.events,
				"onUpdate:modelValue": m[5] ||= (t) => e.events = t,
				items: e.names,
				label: e.$pgettext("webhooks", "Events"),
				variant: "underlined",
				multiple: "",
				chips: ""
			}, null, 8, [
				"modelValue",
				"items",
				"label"
			]),
			s(C, {
				modelValue: e.name,
				"onUpdate:modelValue": m[6] ||= (t) => e.name = t,
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
var $ = /*#__PURE__*/ b(O, [["render", Q]]);
//#endregion
export { $ as default };
