import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, mergeProps as c, openBlock as l, renderList as u, resolveComponent as d, toDisplayString as f, withCtx as p, withModifiers as m } from "vue";
//#region node_modules/@mdi/js/mdi.js
var h = "M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z", g = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", _ = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", ee = "M10.59,13.41C11,13.8 11,14.44 10.59,14.83C10.2,15.22 9.56,15.22 9.17,14.83C7.22,12.88 7.22,9.71 9.17,7.76V7.76L12.71,4.22C14.66,2.27 17.83,2.27 19.78,4.22C21.73,6.17 21.73,9.34 19.78,11.29L18.29,12.78C18.3,11.96 18.17,11.14 17.89,10.36L18.36,9.88C19.54,8.71 19.54,6.81 18.36,5.64C17.19,4.46 15.29,4.46 14.12,5.64L10.59,9.17C9.41,10.34 9.41,12.24 10.59,13.41M13.41,9.17C13.8,8.78 14.44,8.78 14.83,9.17C16.78,11.12 16.78,14.29 14.83,16.24V16.24L11.29,19.78C9.34,21.73 6.17,21.73 4.22,19.78C2.27,17.83 2.27,14.66 4.22,12.71L5.71,11.22C5.7,12.04 5.83,12.86 6.11,13.65L5.64,14.12C4.46,15.29 4.46,17.19 5.64,18.36C6.81,19.54 8.71,19.54 9.88,18.36L13.41,14.83C14.59,13.66 14.59,11.76 13.41,10.59C13,10.2 13,9.56 13.41,9.17Z", v = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", y = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", b = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", x = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", S = "M2,21L23,12L2,3V10L17,12L2,14V21Z", C = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, w = e`
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
`, T = e`
  query CmsWebhooks {
    cmsWebhooks {
      ...CmsWebhookFields
    }
    cmsWebhookEvents
    cmsWebhookServer {
      enabled
      blocked
      stalled_since
    }
  }
  ${w}
`, E = e`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${w}
`, D = e`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${w}
`, O = e`
  mutation ReplaceWebhook($id: ID!, $url: String!) {
    replaceWebhook(id: $id, url: $url) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${w}
`, k = e`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${w}
`, A = e`
  mutation PingWebhook($id: ID!) {
    pingWebhook(id: $id) {
      success
      status
      reason
    }
  }
`, j = e`
  mutation DropWebhook($id: [ID!]!) {
    dropWebhook(id: $id)
  }
`, M = {
	name: "WebhookList",
	inject: ["apollo", "messages"],
	data: () => ({
		dialog: !1,
		replaceDialog: !1,
		secretDialog: !1,
		loading: !0,
		saving: !1,
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
			blocked: null,
			stalled_since: null
		}
	}),
	setup() {
		return {
			mdiDelete: h,
			mdiDotsVertical: g,
			mdiKeyVariant: _,
			mdiLinkVariant: ee,
			mdiMagnify: v,
			mdiPencil: y,
			mdiPlus: b,
			mdiRefresh: x,
			mdiSend: S
		};
	},
	computed: {
		serverText() {
			return this.server.enabled ? this.server.blocked ? this.$pgettext("webhooks", "All deliveries are blocked by the server configuration") : this.server.stalled_since ? this.$pgettext("webhooks", "No queued delivery was processed since %{date}, check the queue worker", { date: this.dateText(this.server.stalled_since) }) : "" : this.$pgettext("webhooks", "Webhooks are disabled by the server configuration, no events are sent");
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
		closeSecret() {
			this.secretDialog = !1, this.secret = "";
		},
		dateText(e) {
			return new Date(e).toLocaleString(this.$vuetify.locale.current);
		},
		async load() {
			this.loading = !0;
			try {
				let { data: e } = await this.apollo.query({
					query: T,
					fetchPolicy: "network-only"
				});
				this.items = e.cmsWebhooks, this.checked = /* @__PURE__ */ new Set(), this.names = e.cmsWebhookEvents, this.server = e.cmsWebhookServer || {
					enabled: !0,
					blocked: null,
					stalled_since: null
				};
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
		openReplace(e) {
			this.selected = e, this.url = "", this.replaceDialog = !0;
		},
		async save() {
			this.events.length && (this.selected || this.validUrl(this.url)) && await this.change(async () => {
				if (this.selected) {
					let { data: e } = await this.apollo.mutate({
						mutation: D,
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
						mutation: E,
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
		async replace() {
			this.selected && this.validUrl(this.url) && await this.change(async () => {
				let { data: e } = await this.apollo.mutate({
					mutation: O,
					variables: {
						id: this.selected.id,
						url: this.url.trim()
					}
				});
				this.replaceDialog = !1, this.provision(e.replaceWebhook);
			}, this.$pgettext("webhooks", "Error replacing webhook destination"));
		},
		async rotate(e) {
			let t = this.$pgettext("webhooks", "Rotate the secret of this webhook? Receivers must be updated with the new secret.");
			!this.saving && window.confirm(`${t}\n\n${this.label(e)}`) && await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: k,
					variables: { id: e.id }
				});
				this.provision(t.rotateWebhook);
			}, this.$pgettext("webhooks", "Error rotating webhook secret"));
		},
		async ping(e) {
			await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: A,
					variables: { id: e.id }
				}), n = t.pingWebhook;
				n.success ? (this.items = this.items.map((t) => t.id === e.id ? {
					...t,
					paused_until: null
				} : t), this.messages.add(this.$pgettext("webhooks", "Test event delivered") + ` (${n.status})`, "success")) : this.messages.add(this.$pgettext("webhooks", "Test event failed") + ": " + this.reasonText(n.reason, n.status), "error");
			}, this.$pgettext("webhooks", "Test event failed"));
		},
		async remove(e = null) {
			let t = e ? [e.id] : [...this.checked], n = e ? `${this.$pgettext("webhooks", "Delete this webhook?")}\n\n${this.label(e)}` : `${this.$pgettext("webhooks", "Delete")} (${t.length})?`;
			!this.saving && t.length && window.confirm(n) && await this.change(async () => {
				for (let e = 0; e < t.length; e += 100) {
					let n = new Set(t.slice(e, e + 100));
					await this.apollo.mutate({
						mutation: j,
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
				invalid_encryption: this.$pgettext("webhooks", "Can't be decrypted, replace the URL"),
				invalid_policy: this.$pgettext("webhooks", "Blocked by server configuration"),
				invalid_url: this.$pgettext("webhooks", "Not a valid URL"),
				queue_failed: this.$pgettext("webhooks", "Queue unavailable"),
				resolution_failed: this.$pgettext("webhooks", "Host not found"),
				response_headers_too_large: this.$pgettext("webhooks", "Response too large"),
				timeout: this.$pgettext("webhooks", "Request timed out"),
				tls_error: this.$pgettext("webhooks", "Secure connection failed")
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
		provision(e) {
			this.put(e.webhook), this.secret = e.secret, this.secretDialog = !0;
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
}, Q = { class: "btn-group" }, te = { class: "webhook-status d-flex align-center ga-2" }, ne = { class: "webhook-status-label text-no-wrap" }, re = { class: "webhook-current text-medium-emphasis mt-4 mb-4" }, ie = {
	key: 0,
	class: "webhook-current text-medium-emphasis mb-4"
};
function $(e, h, g, _, ee, v) {
	let y = d("v-alert"), b = d("v-checkbox-btn"), x = d("v-btn"), S = d("v-list-item"), C = d("CmsActionMenu"), w = d("v-text-field"), T = d("v-select"), E = d("v-divider"), D = d("v-chip"), O = d("v-list"), k = d("CmsLoadingSpinner"), A = d("v-sheet"), j = d("v-container"), M = d("v-switch"), $ = d("CmsDialog");
	return l(), i(t, null, [
		s(j, { class: "webhook-list" }, {
			default: p(() => [s(A, { class: "box scroll" }, {
				default: p(() => [
					a("p", N, f(e.$pgettext("webhooks", "Send signed notifications when published content changes.")), 1),
					v.serverText ? (l(), n(y, {
						key: 0,
						type: "warning",
						variant: "tonal",
						class: "webhook-server mb-4"
					}, {
						default: p(() => [o(f(v.serverText), 1)]),
						_: 1
					})) : r("", !0),
					a("div", P, [
						a("div", F, [
							s(b, {
								"model-value": e.checked.size > 0,
								onClick: m(v.toggle, ["stop"]),
								"aria-label": e.$pgettext("webhooks", "Toggle selection")
							}, null, 8, [
								"model-value",
								"onClick",
								"aria-label"
							]),
							s(C, null, {
								activator: p(({ props: t, label: n }) => [s(x, c(t, {
									disabled: !e.checked.size,
									title: n,
									icon: _.mdiDotsVertical,
									variant: "text"
								}), null, 16, [
									"disabled",
									"title",
									"icon"
								])]),
								default: p(() => [s(S, null, {
									default: p(() => [s(x, {
										"prepend-icon": _.mdiDelete,
										disabled: e.saving,
										variant: "text",
										onClick: h[0] ||= (e) => v.remove()
									}, {
										default: p(() => [o(f(e.$pgettext("webhooks", "Delete")) + " (" + f(e.checked.size) + ")", 1)]),
										_: 1
									}, 8, ["prepend-icon", "disabled"])]),
									_: 1
								})]),
								_: 1
							}),
							s(x, {
								title: e.$pgettext("webhooks", "Add webhook"),
								disabled: e.loading,
								icon: _.mdiPlus,
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
							modelValue: e.term,
							"onUpdate:modelValue": h[1] ||= (t) => e.term = t,
							"prepend-inner-icon": _.mdiMagnify,
							label: e.$pgettext("webhooks", "Search for"),
							variant: "underlined",
							"hide-details": "",
							clearable: ""
						}, null, 8, [
							"modelValue",
							"prepend-inner-icon",
							"label"
						]), s(T, {
							modelValue: e.statusFilter,
							"onUpdate:modelValue": h[2] ||= (t) => e.statusFilter = t,
							items: v.statusItems,
							label: e.$pgettext("webhooks", "Status"),
							variant: "underlined",
							"hide-details": ""
						}, null, 8, [
							"modelValue",
							"items",
							"label"
						])]),
						a("div", L, [s(x, {
							title: e.$pgettext("webhooks", "Refresh"),
							icon: _.mdiRefresh,
							loading: e.loading,
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
					s(O, {
						class: "items",
						role: "list"
					}, {
						default: p(() => [(l(!0), i(t, null, u(v.filtered, (t) => (l(), n(S, {
							key: t.id,
							class: "border-b rounded-0 pa-1",
							role: "listitem"
						}, {
							default: p(() => [a("div", R, [a("div", z, [s(b, {
								"model-value": e.checked.has(t.id),
								"onUpdate:modelValue": (e) => v.toggleCheck(t),
								"aria-label": e.$pgettext("webhooks", "Toggle selection")
							}, null, 8, [
								"model-value",
								"onUpdate:modelValue",
								"aria-label"
							]), s(C, null, {
								activator: p(({ props: e, label: t }) => [s(x, c({ ref_for: !0 }, e, {
									title: t,
									icon: _.mdiDotsVertical,
									variant: "text"
								}), null, 16, ["title", "icon"])]),
								default: p(() => [
									s(S, null, {
										default: p(() => [s(x, {
											"prepend-icon": _.mdiPencil,
											variant: "text",
											onClick: (e) => v.openEdit(t)
										}, {
											default: p(() => [o(f(e.$pgettext("webhooks", "Edit")), 1)]),
											_: 1
										}, 8, ["prepend-icon", "onClick"])]),
										_: 2
									}, 1024),
									s(S, null, {
										default: p(() => [s(x, {
											"prepend-icon": _.mdiLinkVariant,
											variant: "text",
											onClick: (e) => v.openReplace(t)
										}, {
											default: p(() => [o(f(e.$pgettext("webhooks", "Replace")), 1)]),
											_: 1
										}, 8, ["prepend-icon", "onClick"])]),
										_: 2
									}, 1024),
									s(S, null, {
										default: p(() => [s(x, {
											"prepend-icon": _.mdiKeyVariant,
											disabled: e.saving || v.undecryptable(t),
											class: "btn-rotate",
											variant: "text",
											onClick: (e) => v.rotate(t)
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
									s(S, null, {
										default: p(() => [s(x, {
											"prepend-icon": _.mdiSend,
											disabled: e.saving,
											class: "btn-ping",
											variant: "text",
											onClick: (e) => v.ping(t)
										}, {
											default: p(() => [o(f(e.$pgettext("webhooks", "Send test event")), 1)]),
											_: 1
										}, 8, [
											"prepend-icon",
											"disabled",
											"onClick"
										])]),
										_: 2
									}, 1024),
									s(E),
									s(S, null, {
										default: p(() => [s(x, {
											"prepend-icon": _.mdiDelete,
											disabled: e.saving,
											variant: "text",
											onClick: (e) => v.remove(t)
										}, {
											default: p(() => [o(f(e.$pgettext("webhooks", "Delete")), 1)]),
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
								onClick: m((e) => v.openEdit(t), ["prevent"])
							}, [a("div", V, [
								a("div", H, [a("span", U, f(t.name || t.endpoint), 1)]),
								t.name ? (l(), i("div", W, f(t.endpoint), 1)) : r("", !0),
								a("div", G, f(t.events.join(", ")), 1)
							]), a("div", K, [
								a("div", null, [s(D, {
									color: t.status ? "success" : void 0,
									size: "small"
								}, {
									default: p(() => [o(f(t.status ? e.$pgettext("webhooks", "Active") : e.$pgettext("webhooks", "Inactive")), 1)]),
									_: 2
								}, 1032, ["color"])]),
								a("div", q, f(e.$pgettext("webhooks", "Last success")) + ": " + f(v.successText(t)), 1),
								a("div", J, f(e.$pgettext("webhooks", "Last error")) + ": " + f(v.errorText(t)), 1),
								t.paused_until ? (l(), i("div", Y, f(e.$pgettext("webhooks", "Paused until")) + ": " + f(v.dateText(t.paused_until)), 1)) : r("", !0)
							])], 8, B)])]),
							_: 2
						}, 1024))), 128))]),
						_: 1
					}),
					e.loading ? (l(), i("p", X, [o(f(e.$pgettext("webhooks", "Loading")) + " ", 1), s(k, {
						width: "32",
						height: "32"
					})])) : v.filtered.length ? r("", !0) : (l(), i("p", Z, f(e.items.length ? e.$pgettext("webhooks", "No entries found") : e.$pgettext("webhooks", "No webhooks configured.")), 1)),
					a("div", Q, [s(x, {
						title: e.$pgettext("webhooks", "Add webhook"),
						disabled: e.loading,
						icon: _.mdiPlus,
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
		}),
		s($, {
			modelValue: e.dialog,
			"onUpdate:modelValue": h[7] ||= (t) => e.dialog = t,
			title: e.selected ? e.$pgettext("webhooks", "Edit webhook") : e.$pgettext("webhooks", "Add webhook"),
			persistent: !!e.secret,
			"max-width": "640",
			onAfterLeave: h[8] ||= (t) => e.secret = ""
		}, {
			actions: p(({ close: n }) => [e.secret ? (l(), i(t, { key: 0 }, [s(x, {
				variant: "outlined",
				onClick: n
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Done")), 1)]),
				_: 1
			}, 8, ["onClick"]), s(x, {
				color: "primary",
				variant: "tonal",
				onClick: v.copySecret,
				active: ""
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Copy secret")), 1)]),
				_: 1
			}, 8, ["onClick"])], 64)) : (l(), i(t, { key: 1 }, [s(x, {
				variant: "outlined",
				onClick: n
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]), s(x, {
				color: "primary",
				variant: "tonal",
				disabled: !e.events.length || !e.selected && !v.validUrl(e.url),
				loading: e.saving,
				onClick: v.save,
				active: ""
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Save")), 1)]),
				_: 1
			}, 8, [
				"disabled",
				"loading",
				"onClick"
			])], 64))]),
			default: p(() => [e.secret ? (l(), i(t, { key: 0 }, [s(y, {
				type: "warning",
				variant: "tonal",
				class: "mb-4"
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Copy this secret now. It will not be shown again.")), 1)]),
				_: 1
			}), s(w, {
				"model-value": e.secret,
				class: "webhook-secret",
				variant: "underlined",
				readonly: ""
			}, null, 8, ["model-value"])], 64)) : (l(), i(t, { key: 1 }, [
				a("div", te, [s(M, {
					modelValue: e.status,
					"onUpdate:modelValue": h[3] ||= (t) => e.status = t,
					"aria-label": e.$pgettext("webhooks", "Active"),
					disabled: !!e.selected && !e.selected.status && v.undecryptable(e.selected),
					color: "success",
					density: "compact",
					"hide-details": "",
					class: "flex-grow-0 flex-shrink-0"
				}, null, 8, [
					"modelValue",
					"aria-label",
					"disabled"
				]), a("span", ne, f(e.$pgettext("webhooks", "Active")), 1)]),
				e.selected ? (l(), i(t, { key: 1 }, [a("p", re, f(e.$pgettext("webhooks", "Current destination")) + ": " + f(e.selected.endpoint), 1), v.undecryptable(e.selected) ? (l(), n(y, {
					key: 0,
					type: "warning",
					variant: "tonal",
					class: "webhook-undecryptable mb-4"
				}, {
					default: p(() => [o(f(v.reasonText("invalid_encryption")), 1)]),
					_: 1
				})) : r("", !0)], 64)) : (l(), n(w, {
					key: 0,
					modelValue: e.url,
					"onUpdate:modelValue": h[4] ||= (t) => e.url = t,
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
				s(w, {
					modelValue: e.name,
					"onUpdate:modelValue": h[5] ||= (t) => e.name = t,
					label: e.$pgettext("webhooks", "Name"),
					class: "webhook-name",
					variant: "underlined",
					maxlength: "100"
				}, null, 8, ["modelValue", "label"]),
				s(T, {
					modelValue: e.events,
					"onUpdate:modelValue": h[6] ||= (t) => e.events = t,
					items: e.names,
					label: e.$pgettext("webhooks", "Events"),
					variant: "underlined",
					multiple: "",
					chips: ""
				}, null, 8, [
					"modelValue",
					"items",
					"label"
				])
			], 64))]),
			_: 1
		}, 8, [
			"modelValue",
			"title",
			"persistent"
		]),
		s($, {
			modelValue: e.replaceDialog,
			"onUpdate:modelValue": h[10] ||= (t) => e.replaceDialog = t,
			title: e.$pgettext("webhooks", "Replace webhook destination"),
			"max-width": "640"
		}, {
			actions: p(({ close: t }) => [s(x, {
				variant: "outlined",
				onClick: t
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]), s(x, {
				color: "primary",
				variant: "tonal",
				disabled: !v.validUrl(e.url),
				loading: e.saving,
				onClick: v.replace,
				active: ""
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Replace")), 1)]),
				_: 1
			}, 8, [
				"disabled",
				"loading",
				"onClick"
			])]),
			default: p(() => [
				e.selected ? (l(), i("p", ie, f(e.$pgettext("webhooks", "Current destination")) + ": " + f(v.label(e.selected)), 1)) : r("", !0),
				s(w, {
					modelValue: e.url,
					"onUpdate:modelValue": h[9] ||= (t) => e.url = t,
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
				]),
				s(y, {
					type: "warning",
					variant: "tonal"
				}, {
					default: p(() => [o(f(e.$pgettext("webhooks", "Replacing the destination rotates the secret and disables the webhook.")), 1)]),
					_: 1
				})
			]),
			_: 1
		}, 8, ["modelValue", "title"]),
		s($, {
			modelValue: e.secretDialog,
			"onUpdate:modelValue": [h[11] ||= (t) => e.secretDialog = t, h[12] ||= (e) => !e && v.closeSecret()],
			title: e.$pgettext("webhooks", "Webhook secret"),
			"max-width": "640",
			persistent: ""
		}, {
			actions: p(() => [s(x, {
				variant: "outlined",
				onClick: v.closeSecret
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Done")), 1)]),
				_: 1
			}, 8, ["onClick"]), s(x, {
				color: "primary",
				variant: "tonal",
				onClick: v.copySecret,
				active: ""
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Copy secret")), 1)]),
				_: 1
			}, 8, ["onClick"])]),
			default: p(() => [s(y, {
				type: "warning",
				variant: "tonal",
				class: "mb-4"
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Copy this secret now. It will not be shown again.")), 1)]),
				_: 1
			}), s(w, {
				"model-value": e.secret,
				variant: "underlined",
				readonly: ""
			}, null, 8, ["model-value"])]),
			_: 1
		}, 8, ["modelValue", "title"])
	], 64);
}
var ae = /*#__PURE__*/ C(M, [["render", $]]);
//#endregion
export { ae as default };
