import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, mergeProps as c, openBlock as l, renderList as ee, resolveComponent as u, resolveDynamicComponent as d, toDisplayString as f, withCtx as p, withModifiers as m } from "vue";
//#region node_modules/@mdi/js/mdi.js
var h = "M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z", te = "M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z", g = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", _ = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", v = "M10.59,13.41C11,13.8 11,14.44 10.59,14.83C10.2,15.22 9.56,15.22 9.17,14.83C7.22,12.88 7.22,9.71 9.17,7.76V7.76L12.71,4.22C14.66,2.27 17.83,2.27 19.78,4.22C21.73,6.17 21.73,9.34 19.78,11.29L18.29,12.78C18.3,11.96 18.17,11.14 17.89,10.36L18.36,9.88C19.54,8.71 19.54,6.81 18.36,5.64C17.19,4.46 15.29,4.46 14.12,5.64L10.59,9.17C9.41,10.34 9.41,12.24 10.59,13.41M13.41,9.17C13.8,8.78 14.44,8.78 14.83,9.17C16.78,11.12 16.78,14.29 14.83,16.24V16.24L11.29,19.78C9.34,21.73 6.17,21.73 4.22,19.78C2.27,17.83 2.27,14.66 4.22,12.71L5.71,11.22C5.7,12.04 5.83,12.86 6.11,13.65L5.64,14.12C4.46,15.29 4.46,17.19 5.64,18.36C6.81,19.54 8.71,19.54 9.88,18.36L13.41,14.83C14.59,13.66 14.59,11.76 13.41,10.59C13,10.2 13,9.56 13.41,9.17Z", y = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", b = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", x = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", S = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", C = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, w = e`
  fragment CmsWebhookFields on CmsWebhook {
    id
    status
    failures
    endpoint
    events
    last_error
    last_success_at
  }
`, T = e`
  query CmsWebhooks {
    cmsWebhooks {
      ...CmsWebhookFields
    }
    cmsWebhookEvents
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
  mutation DropWebhook($id: [ID!]!) {
    dropWebhook(id: $id)
  }
`, j = {
	name: "WebhookList",
	inject: ["apollo", "messages"],
	data: () => ({
		actions: !1,
		dialog: !1,
		replaceDialog: !1,
		secretDialog: !1,
		loading: !0,
		saving: !1,
		items: [],
		checked: /* @__PURE__ */ new Set(),
		menu: [],
		names: [],
		selected: null,
		term: "",
		statusFilter: null,
		url: "",
		events: [],
		status: !1,
		secret: ""
	}),
	setup() {
		return {
			mdiClose: h,
			mdiDelete: te,
			mdiDotsVertical: g,
			mdiKeyVariant: _,
			mdiLinkVariant: v,
			mdiMagnify: y,
			mdiPencil: b,
			mdiPlus: x,
			mdiRefresh: S
		};
	},
	computed: {
		filtered() {
			let e = (this.term ?? "").trim().toLocaleLowerCase();
			return this.items.filter((t) => this.statusFilter !== null && t.status !== this.statusFilter ? !1 : !e || t.endpoint.toLocaleLowerCase().includes(e) || t.events.some((t) => t.toLocaleLowerCase().includes(e)));
		},
		statusItems() {
			return [
				{
					title: this.$gettext("All"),
					value: null
				},
				{
					title: this.$gettext("Active"),
					value: !0
				},
				{
					title: this.$gettext("Inactive"),
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
		async load() {
			this.loading = !0;
			try {
				let { data: e } = await this.apollo.query({
					query: T,
					fetchPolicy: "network-only"
				});
				this.items = e.cmsWebhooks, this.checked = /* @__PURE__ */ new Set(), this.names = e.cmsWebhookEvents;
			} catch (e) {
				this.messages.add(this.$gettext("Error fetching webhooks") + ":\n" + e, "error");
			} finally {
				this.loading = !1;
			}
		},
		openAdd() {
			this.selected = null, this.url = "", this.events = [], this.status = !1, this.dialog = !0;
		},
		openEdit(e) {
			this.selected = e, this.events = [...e.events], this.status = e.status, this.dialog = !0;
		},
		openReplace(e) {
			this.selected = e, this.url = "", this.replaceDialog = !0;
		},
		async save() {
			this.events.length && (this.selected || this.url.trim()) && await this.change(async () => {
				if (this.selected) {
					let { data: e } = await this.apollo.mutate({
						mutation: D,
						variables: {
							id: this.selected.id,
							input: {
								events: this.events,
								status: this.status
							}
						}
					});
					this.put(e.saveWebhook);
				} else {
					let { data: e } = await this.apollo.mutate({
						mutation: E,
						variables: { input: {
							url: this.url.trim(),
							events: this.events
						} }
					});
					this.provision(e.addWebhook);
				}
				this.dialog = !1;
			}, this.$gettext("Error saving webhook"));
		},
		async replace() {
			this.selected && this.url.trim() && await this.change(async () => {
				let { data: e } = await this.apollo.mutate({
					mutation: O,
					variables: {
						id: this.selected.id,
						url: this.url.trim()
					}
				});
				this.replaceDialog = !1, this.provision(e.replaceWebhook);
			}, this.$gettext("Error replacing webhook destination"));
		},
		async rotate(e) {
			await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: k,
					variables: { id: e.id }
				});
				this.provision(t.rotateWebhook);
			}, this.$gettext("Error rotating webhook secret"));
		},
		async remove(e = null) {
			let t = e ? [e.id] : [...this.checked], n = e ? this.$gettext("Delete this webhook?") : `${this.$gettext("Delete")} (${t.length})?`;
			!this.saving && t.length && window.confirm(n) && await this.change(async () => {
				await this.apollo.mutate({
					mutation: A,
					variables: { id: t }
				});
				let e = new Set(t);
				this.items = this.items.filter((t) => !e.has(t.id)), this.checked = new Set([...this.checked].filter((t) => !e.has(t)));
			}, this.$gettext("Error deleting webhook"));
		},
		async copySecret() {
			try {
				await navigator.clipboard.writeText(this.secret), this.messages.add(this.$gettext("Secret copied"), "success");
			} catch {
				this.messages.add(this.$gettext("Unable to copy secret"), "error");
			}
		},
		errorText(e) {
			if (!e.last_error) return this.$gettext("None");
			let t = e.last_error.status ? ` (${e.last_error.status})` : "";
			return `${{
				destination_not_allowed: this.$gettext("Access denied"),
				invalid_header: this.$gettext("Value has invalid format"),
				invalid_url: this.$gettext("Not a valid URL")
			}[e.last_error.reason] || this.$gettext("Delivery failed")}${t}`;
		},
		successText(e) {
			return e.last_success_at ? new Date(e.last_success_at).toLocaleString(this.$vuetify.locale.current) : this.$gettext("None");
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
			t.delete(e.id), this.items = [e, ...this.items.filter((t) => t.id !== e.id)], this.checked = t;
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
}, M = { class: "v-sheet box scroll" }, N = { class: "text-medium-emphasis mb-4" }, P = { class: "header" }, F = { class: "bulk" }, I = { class: "v-list-item" }, L = { class: "search" }, R = { class: "layout" }, z = {
	class: "v-list items",
	role: "list"
}, B = { class: "d-flex align-center w-100" }, V = { class: "d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2" }, H = ["onClick"], U = { class: "v-list-item" }, W = { class: "v-list-item" }, G = { class: "v-list-item" }, K = { class: "v-list-item" }, q = ["onClick"], J = { class: "item-text" }, Y = { class: "item-head" }, X = { class: "item-title" }, Z = { class: "item-subtitle" }, ne = { class: "item-aux text-end" }, Q = { class: "item-subtitle" }, re = { class: "item-subtitle" }, ie = {
	key: 0,
	class: "loading"
}, ae = {
	key: 1,
	class: "notfound"
}, oe = { class: "btn-group" };
function $(e, h, te, g, _, v) {
	let y = u("v-checkbox-btn"), b = u("v-btn"), x = u("v-spacer"), S = u("v-card-title"), C = u("v-card"), w = u("v-text-field"), T = u("v-select"), E = u("v-chip"), D = u("v-container"), O = u("v-switch"), k = u("v-alert"), A = u("v-card-text"), j = u("v-card-actions"), $ = u("v-dialog");
	return l(), i(t, null, [
		s(D, { class: "webhook-list" }, {
			default: p(() => [a("div", M, [
				a("p", N, f(e.$gettext("Send signed notifications when published content changes.")), 1),
				a("div", P, [
					a("div", F, [
						s(y, {
							"model-value": e.checked.size > 0,
							onClick: m(v.toggle, ["stop"]),
							"aria-label": e.$gettext("Toggle selection")
						}, null, 8, [
							"model-value",
							"onClick",
							"aria-label"
						]),
						(l(), n(d(e.$vuetify.display.xs ? "v-dialog" : "v-menu"), {
							modelValue: e.actions,
							"onUpdate:modelValue": h[3] ||= (t) => e.actions = t,
							"aria-label": e.$gettext("Actions"),
							transition: "scale-transition",
							location: "end center",
							"max-width": "300"
						}, {
							activator: p(({ props: t }) => [s(b, c(t, {
								disabled: !e.checked.size,
								title: e.$gettext("Actions"),
								icon: g.mdiDotsVertical,
								variant: "text"
							}), null, 16, [
								"disabled",
								"title",
								"icon"
							])]),
							default: p(() => [s(C, null, {
								default: p(() => [s(S, { class: "d-flex align-center" }, {
									default: p(() => [
										a("span", null, f(e.$gettext("Actions")), 1),
										s(x),
										s(b, {
											icon: g.mdiClose,
											"aria-label": e.$gettext("Close"),
											onClick: h[0] ||= (t) => e.actions = !1
										}, null, 8, ["icon", "aria-label"])
									]),
									_: 1
								}), a("div", {
									class: "v-list",
									onClick: h[2] ||= (t) => e.actions = !1
								}, [a("div", I, [s(b, {
									"prepend-icon": g.mdiDelete,
									disabled: e.saving,
									variant: "text",
									onClick: h[1] ||= (e) => v.remove()
								}, {
									default: p(() => [o(f(e.$gettext("Delete")) + " (" + f(e.checked.size) + ")", 1)]),
									_: 1
								}, 8, ["prepend-icon", "disabled"])])])]),
								_: 1
							})]),
							_: 1
						}, 8, ["modelValue", "aria-label"])),
						s(b, {
							title: e.$gettext("Add webhook"),
							disabled: e.loading,
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
					a("div", L, [s(w, {
						modelValue: e.term,
						"onUpdate:modelValue": h[4] ||= (t) => e.term = t,
						"prepend-inner-icon": g.mdiMagnify,
						label: e.$gettext("Search for"),
						variant: "underlined",
						"hide-details": "",
						clearable: ""
					}, null, 8, [
						"modelValue",
						"prepend-inner-icon",
						"label"
					]), s(T, {
						modelValue: e.statusFilter,
						"onUpdate:modelValue": h[5] ||= (t) => e.statusFilter = t,
						items: v.statusItems,
						label: e.$gettext("Status"),
						variant: "underlined",
						"hide-details": ""
					}, null, 8, [
						"modelValue",
						"items",
						"label"
					])]),
					a("div", R, [s(b, {
						title: e.$gettext("Refresh"),
						icon: g.mdiRefresh,
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
				a("div", z, [(l(!0), i(t, null, ee(v.filtered, (t, r) => (l(), i("div", {
					key: t.id,
					class: "v-list-item border-b rounded-0 pa-1",
					role: "listitem"
				}, [a("div", B, [a("div", V, [s(y, {
					"model-value": e.checked.has(t.id),
					"onUpdate:modelValue": (e) => v.toggleCheck(t),
					"aria-label": e.$gettext("Toggle selection")
				}, null, 8, [
					"model-value",
					"onUpdate:modelValue",
					"aria-label"
				]), (l(), n(d(e.$vuetify.display.xs ? "v-dialog" : "v-menu"), {
					modelValue: e.menu[r],
					"onUpdate:modelValue": (t) => e.menu[r] = t,
					"aria-label": e.$gettext("Actions"),
					transition: "scale-transition",
					location: "end center",
					"max-width": "300"
				}, {
					activator: p(({ props: t }) => [s(b, c({ ref_for: !0 }, t, {
						title: e.$gettext("Actions"),
						icon: g.mdiDotsVertical,
						variant: "text"
					}), null, 16, ["title", "icon"])]),
					default: p(() => [s(C, null, {
						default: p(() => [s(S, { class: "d-flex align-center" }, {
							default: p(() => [
								a("span", null, f(e.$gettext("Actions")), 1),
								s(x),
								s(b, {
									icon: g.mdiClose,
									"aria-label": e.$gettext("Close"),
									onClick: (t) => e.menu[r] = !1
								}, null, 8, [
									"icon",
									"aria-label",
									"onClick"
								])
							]),
							_: 2
						}, 1024), a("div", {
							class: "v-list",
							onClick: (t) => e.menu[r] = !1
						}, [
							a("div", U, [s(b, {
								"prepend-icon": g.mdiPencil,
								variant: "text",
								onClick: (e) => v.openEdit(t)
							}, {
								default: p(() => [o(f(e.$gettext("Edit")), 1)]),
								_: 1
							}, 8, ["prepend-icon", "onClick"])]),
							a("div", W, [s(b, {
								"prepend-icon": g.mdiLinkVariant,
								variant: "text",
								onClick: (e) => v.openReplace(t)
							}, {
								default: p(() => [o(f(e.$gettext("Replace")), 1)]),
								_: 1
							}, 8, ["prepend-icon", "onClick"])]),
							a("div", G, [s(b, {
								"prepend-icon": g.mdiKeyVariant,
								variant: "text",
								onClick: (e) => v.rotate(t)
							}, {
								default: p(() => [o(f(e.$gettext("Rotate")), 1)]),
								_: 1
							}, 8, ["prepend-icon", "onClick"])]),
							h[16] ||= a("div", { class: "border-t" }, null, -1),
							a("div", K, [s(b, {
								"prepend-icon": g.mdiDelete,
								disabled: e.saving,
								variant: "text",
								onClick: (e) => v.remove(t)
							}, {
								default: p(() => [o(f(e.$gettext("Delete")), 1)]),
								_: 1
							}, 8, [
								"prepend-icon",
								"disabled",
								"onClick"
							])])
						], 8, H)]),
						_: 2
					}, 1024)]),
					_: 2
				}, 1032, [
					"modelValue",
					"onUpdate:modelValue",
					"aria-label"
				]))]), a("a", {
					href: "#",
					class: "item-content",
					onClick: m((e) => v.openEdit(t), ["prevent"])
				}, [a("div", J, [a("div", Y, [a("span", X, f(t.endpoint), 1)]), a("div", Z, f(t.events.join(", ")), 1)]), a("div", ne, [
					a("div", null, [s(E, {
						color: t.status ? "success" : void 0,
						size: "small"
					}, {
						default: p(() => [o(f(t.status ? e.$gettext("Active") : e.$gettext("Inactive")), 1)]),
						_: 2
					}, 1032, ["color"])]),
					a("div", Q, f(e.$gettext("Last success")) + ": " + f(v.successText(t)), 1),
					a("div", re, f(e.$gettext("Failures")) + ": " + f(t.failures) + " · " + f(e.$gettext("Last error")) + ": " + f(v.errorText(t)), 1)
				])], 8, q)])]))), 128))]),
				e.loading ? (l(), i("p", ie, [o(f(e.$gettext("Loading")) + " ", 1), h[17] ||= a("svg", {
					class: "spinner",
					width: "32",
					height: "32",
					fill: "currentColor",
					viewBox: "0 0 24 24",
					xmlns: "http://www.w3.org/2000/svg"
				}, [
					a("circle", {
						class: "spin1",
						cx: "4",
						cy: "12",
						r: "3"
					}),
					a("circle", {
						class: "spin1 spin2",
						cx: "12",
						cy: "12",
						r: "3"
					}),
					a("circle", {
						class: "spin1 spin3",
						cx: "20",
						cy: "12",
						r: "3"
					})
				], -1)])) : v.filtered.length ? r("", !0) : (l(), i("p", ae, f(e.items.length ? e.$gettext("No entries found") : e.$gettext("No webhooks configured.")), 1)),
				a("div", oe, [s(b, {
					title: e.$gettext("Add webhook"),
					disabled: e.loading,
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
			])]),
			_: 1
		}),
		s($, {
			modelValue: e.dialog,
			"onUpdate:modelValue": h[10] ||= (t) => e.dialog = t,
			"max-width": "640"
		}, {
			default: p(() => [s(C, null, {
				default: p(() => [
					s(S, { class: "d-flex align-center" }, {
						default: p(() => [
							a("span", null, f(e.selected ? e.$gettext("Edit webhook") : e.$gettext("Add webhook")), 1),
							s(x),
							s(b, {
								icon: g.mdiClose,
								"aria-label": e.$gettext("Close"),
								onClick: h[6] ||= (t) => e.dialog = !1
							}, null, 8, ["icon", "aria-label"])
						]),
						_: 1
					}),
					s(A, null, {
						default: p(() => [
							e.selected ? r("", !0) : (l(), n(w, {
								key: 0,
								modelValue: e.url,
								"onUpdate:modelValue": h[7] ||= (t) => e.url = t,
								label: e.$gettext("HTTPS endpoint URL"),
								variant: "underlined",
								maxlength: "500",
								autofocus: ""
							}, null, 8, ["modelValue", "label"])),
							s(T, {
								modelValue: e.events,
								"onUpdate:modelValue": h[8] ||= (t) => e.events = t,
								items: e.names,
								label: e.$gettext("Events"),
								variant: "underlined",
								multiple: "",
								chips: ""
							}, null, 8, [
								"modelValue",
								"items",
								"label"
							]),
							e.selected ? (l(), n(O, {
								key: 1,
								modelValue: e.status,
								"onUpdate:modelValue": h[9] ||= (t) => e.status = t,
								color: "success",
								label: e.$gettext("Active")
							}, null, 8, ["modelValue", "label"])) : (l(), n(k, {
								key: 2,
								type: "info",
								variant: "tonal"
							}, {
								default: p(() => [o(f(e.$gettext("New webhooks are inactive until you save them as active.")), 1)]),
								_: 1
							}))
						]),
						_: 1
					}),
					s(j, null, {
						default: p(() => [s(x), s(b, {
							variant: "outlined",
							loading: e.saving,
							onClick: v.save
						}, {
							default: p(() => [o(f(e.$gettext("Save")), 1)]),
							_: 1
						}, 8, ["loading", "onClick"])]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"]),
		s($, {
			modelValue: e.replaceDialog,
			"onUpdate:modelValue": h[13] ||= (t) => e.replaceDialog = t,
			"max-width": "640"
		}, {
			default: p(() => [s(C, null, {
				default: p(() => [
					s(S, { class: "d-flex align-center" }, {
						default: p(() => [
							a("span", null, f(e.$gettext("Replace webhook destination")), 1),
							s(x),
							s(b, {
								icon: g.mdiClose,
								"aria-label": e.$gettext("Close"),
								onClick: h[11] ||= (t) => e.replaceDialog = !1
							}, null, 8, ["icon", "aria-label"])
						]),
						_: 1
					}),
					s(A, null, {
						default: p(() => [s(w, {
							modelValue: e.url,
							"onUpdate:modelValue": h[12] ||= (t) => e.url = t,
							label: e.$gettext("HTTPS endpoint URL"),
							variant: "underlined",
							maxlength: "500",
							autofocus: ""
						}, null, 8, ["modelValue", "label"]), s(k, {
							type: "warning",
							variant: "tonal"
						}, {
							default: p(() => [o(f(e.$gettext("Replacing the destination rotates the secret and disables the webhook.")), 1)]),
							_: 1
						})]),
						_: 1
					}),
					s(j, null, {
						default: p(() => [s(x), s(b, {
							variant: "outlined",
							loading: e.saving,
							onClick: v.replace
						}, {
							default: p(() => [o(f(e.$gettext("Replace")), 1)]),
							_: 1
						}, 8, ["loading", "onClick"])]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"]),
		s($, {
			modelValue: e.secretDialog,
			"onUpdate:modelValue": h[15] ||= (t) => e.secretDialog = t,
			"max-width": "640",
			persistent: ""
		}, {
			default: p(() => [s(C, null, {
				default: p(() => [
					s(S, null, {
						default: p(() => [o(f(e.$gettext("Webhook secret")), 1)]),
						_: 1
					}),
					s(A, null, {
						default: p(() => [s(k, {
							type: "warning",
							variant: "tonal",
							class: "mb-4"
						}, {
							default: p(() => [o(f(e.$gettext("Copy this secret now. It will not be shown again.")), 1)]),
							_: 1
						}), s(w, {
							"model-value": e.secret,
							variant: "underlined",
							readonly: ""
						}, null, 8, ["model-value"])]),
						_: 1
					}),
					s(j, null, {
						default: p(() => [
							s(b, {
								variant: "outlined",
								onClick: v.copySecret
							}, {
								default: p(() => [o(f(e.$gettext("Copy secret")), 1)]),
								_: 1
							}, 8, ["onClick"]),
							s(x),
							s(b, {
								variant: "text",
								onClick: h[14] ||= (t) => {
									e.secretDialog = !1, e.secret = "";
								}
							}, {
								default: p(() => [o(f(e.$gettext("Done")), 1)]),
								_: 1
							})
						]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"])
	], 64);
}
var se = /*#__PURE__*/ C(j, [["render", $]]);
//#endregion
export { se as default };
