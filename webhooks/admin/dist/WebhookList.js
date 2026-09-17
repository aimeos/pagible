import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, mergeProps as c, openBlock as l, renderList as u, resolveComponent as d, toDisplayString as f, withCtx as p, withModifiers as m } from "vue";
//#region node_modules/@mdi/js/mdi.js
var h = "M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z", g = "M12,16A2,2 0 0,1 14,18A2,2 0 0,1 12,20A2,2 0 0,1 10,18A2,2 0 0,1 12,16M12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12A2,2 0 0,1 12,10M12,4A2,2 0 0,1 14,6A2,2 0 0,1 12,8A2,2 0 0,1 10,6A2,2 0 0,1 12,4Z", _ = "M22,18V22H18V19H15V16H12L9.74,13.74C9.19,13.91 8.61,14 8,14A6,6 0 0,1 2,8A6,6 0 0,1 8,2A6,6 0 0,1 14,8C14,8.61 13.91,9.19 13.74,9.74L22,18M7,5A2,2 0 0,0 5,7A2,2 0 0,0 7,9A2,2 0 0,0 9,7A2,2 0 0,0 7,5Z", v = "M10.59,13.41C11,13.8 11,14.44 10.59,14.83C10.2,15.22 9.56,15.22 9.17,14.83C7.22,12.88 7.22,9.71 9.17,7.76V7.76L12.71,4.22C14.66,2.27 17.83,2.27 19.78,4.22C21.73,6.17 21.73,9.34 19.78,11.29L18.29,12.78C18.3,11.96 18.17,11.14 17.89,10.36L18.36,9.88C19.54,8.71 19.54,6.81 18.36,5.64C17.19,4.46 15.29,4.46 14.12,5.64L10.59,9.17C9.41,10.34 9.41,12.24 10.59,13.41M13.41,9.17C13.8,8.78 14.44,8.78 14.83,9.17C16.78,11.12 16.78,14.29 14.83,16.24V16.24L11.29,19.78C9.34,21.73 6.17,21.73 4.22,19.78C2.27,17.83 2.27,14.66 4.22,12.71L5.71,11.22C5.7,12.04 5.83,12.86 6.11,13.65L5.64,14.12C4.46,15.29 4.46,17.19 5.64,18.36C6.81,19.54 8.71,19.54 9.88,18.36L13.41,14.83C14.59,13.66 14.59,11.76 13.41,10.59C13,10.2 13,9.56 13.41,9.17Z", y = "M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z", b = "M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z", x = "M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z", S = "M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z", C = (e, t) => {
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
		events: [],
		status: !1,
		secret: ""
	}),
	setup() {
		return {
			mdiDelete: h,
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
		async load() {
			this.loading = !0;
			try {
				let { data: e } = await this.apollo.query({
					query: T,
					fetchPolicy: "network-only"
				});
				this.items = e.cmsWebhooks, this.checked = /* @__PURE__ */ new Set(), this.names = e.cmsWebhookEvents;
			} catch (e) {
				this.messages.add(this.$pgettext("webhooks", "Error fetching webhooks") + ":\n" + e, "error");
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
			}, this.$pgettext("webhooks", "Error saving webhook"));
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
			}, this.$pgettext("webhooks", "Error replacing webhook destination"));
		},
		async rotate(e) {
			await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: k,
					variables: { id: e.id }
				});
				this.provision(t.rotateWebhook);
			}, this.$pgettext("webhooks", "Error rotating webhook secret"));
		},
		async remove(e = null) {
			let t = e ? [e.id] : [...this.checked], n = e ? this.$pgettext("webhooks", "Delete this webhook?") : `${this.$pgettext("webhooks", "Delete")} (${t.length})?`;
			!this.saving && t.length && window.confirm(n) && await this.change(async () => {
				await this.apollo.mutate({
					mutation: A,
					variables: { id: t }
				});
				let e = new Set(t);
				this.items = this.items.filter((t) => !e.has(t.id)), this.checked = new Set([...this.checked].filter((t) => !e.has(t)));
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
			if (!e.last_error) return this.$pgettext("webhooks", "None");
			let t = e.last_error.status ? ` (${e.last_error.status})` : "";
			return `${{
				destination_not_allowed: this.$pgettext("webhooks", "Access denied"),
				invalid_header: this.$pgettext("webhooks", "Value has invalid format"),
				invalid_url: this.$pgettext("webhooks", "Not a valid URL")
			}[e.last_error.reason] || this.$pgettext("webhooks", "Delivery failed")}${t}`;
		},
		successText(e) {
			return e.last_success_at ? new Date(e.last_success_at).toLocaleString(this.$vuetify.locale.current) : this.$pgettext("webhooks", "None");
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
}, ee = { class: "text-medium-emphasis mb-4" }, M = { class: "header" }, N = { class: "bulk" }, P = { class: "search" }, F = { class: "layout" }, I = { class: "d-flex align-center w-100" }, L = { class: "d-flex flex-column flex-sm-row flex-shrink-0 align-center me-2" }, R = ["onClick"], z = { class: "item-text" }, B = { class: "item-head" }, V = { class: "item-title" }, H = { class: "item-subtitle" }, U = { class: "item-aux text-end" }, W = { class: "item-subtitle" }, G = { class: "item-subtitle" }, K = {
	key: 0,
	class: "loading"
}, q = {
	key: 1,
	class: "notfound"
}, J = { class: "btn-group" }, Y = {
	key: 0,
	class: "webhook-status d-flex align-center ga-2"
}, X = { class: "webhook-status-label text-no-wrap" };
function Z(e, h, g, _, v, y) {
	let b = d("v-checkbox-btn"), x = d("v-btn"), S = d("v-list-item"), C = d("CmsActionMenu"), w = d("v-text-field"), T = d("v-select"), E = d("v-divider"), D = d("v-chip"), O = d("v-list"), k = d("CmsLoadingSpinner"), A = d("v-sheet"), j = d("v-container"), Z = d("v-switch"), Q = d("v-alert"), $ = d("CmsDialog");
	return l(), i(t, null, [
		s(j, { class: "webhook-list" }, {
			default: p(() => [s(A, { class: "box scroll" }, {
				default: p(() => [
					a("p", ee, f(e.$pgettext("webhooks", "Send signed notifications when published content changes.")), 1),
					a("div", M, [
						a("div", N, [
							s(b, {
								"model-value": e.checked.size > 0,
								onClick: m(y.toggle, ["stop"]),
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
										onClick: h[0] ||= (e) => y.remove()
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
								onClick: y.openAdd
							}, null, 8, [
								"title",
								"disabled",
								"icon",
								"onClick"
							])
						]),
						a("div", P, [s(w, {
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
							items: y.statusItems,
							label: e.$pgettext("webhooks", "Status"),
							variant: "underlined",
							"hide-details": ""
						}, null, 8, [
							"modelValue",
							"items",
							"label"
						])]),
						a("div", F, [s(x, {
							title: e.$pgettext("webhooks", "Refresh"),
							icon: _.mdiRefresh,
							loading: e.loading,
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
						default: p(() => [(l(!0), i(t, null, u(y.filtered, (t) => (l(), n(S, {
							key: t.id,
							class: "border-b rounded-0 pa-1",
							role: "listitem"
						}, {
							default: p(() => [a("div", I, [a("div", L, [s(b, {
								"model-value": e.checked.has(t.id),
								"onUpdate:modelValue": (e) => y.toggleCheck(t),
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
											onClick: (e) => y.openEdit(t)
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
											onClick: (e) => y.openReplace(t)
										}, {
											default: p(() => [o(f(e.$pgettext("webhooks", "Replace")), 1)]),
											_: 1
										}, 8, ["prepend-icon", "onClick"])]),
										_: 2
									}, 1024),
									s(S, null, {
										default: p(() => [s(x, {
											"prepend-icon": _.mdiKeyVariant,
											variant: "text",
											onClick: (e) => y.rotate(t)
										}, {
											default: p(() => [o(f(e.$pgettext("webhooks", "Rotate")), 1)]),
											_: 1
										}, 8, ["prepend-icon", "onClick"])]),
										_: 2
									}, 1024),
									s(E),
									s(S, null, {
										default: p(() => [s(x, {
											"prepend-icon": _.mdiDelete,
											disabled: e.saving,
											variant: "text",
											onClick: (e) => y.remove(t)
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
								onClick: m((e) => y.openEdit(t), ["prevent"])
							}, [a("div", z, [a("div", B, [a("span", V, f(t.endpoint), 1)]), a("div", H, f(t.events.join(", ")), 1)]), a("div", U, [
								a("div", null, [s(D, {
									color: t.status ? "success" : void 0,
									size: "small"
								}, {
									default: p(() => [o(f(t.status ? e.$pgettext("webhooks", "Active") : e.$pgettext("webhooks", "Inactive")), 1)]),
									_: 2
								}, 1032, ["color"])]),
								a("div", W, f(e.$pgettext("webhooks", "Last success")) + ": " + f(y.successText(t)), 1),
								a("div", G, f(e.$pgettext("webhooks", "Failures")) + ": " + f(t.failures) + " · " + f(e.$pgettext("webhooks", "Last error")) + ": " + f(y.errorText(t)), 1)
							])], 8, R)])]),
							_: 2
						}, 1024))), 128))]),
						_: 1
					}),
					e.loading ? (l(), i("p", K, [o(f(e.$pgettext("webhooks", "Loading")) + " ", 1), s(k, {
						width: "32",
						height: "32"
					})])) : y.filtered.length ? r("", !0) : (l(), i("p", q, f(e.items.length ? e.$pgettext("webhooks", "No entries found") : e.$pgettext("webhooks", "No webhooks configured.")), 1)),
					a("div", J, [s(x, {
						title: e.$pgettext("webhooks", "Add webhook"),
						disabled: e.loading,
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
		}),
		s($, {
			modelValue: e.dialog,
			"onUpdate:modelValue": h[6] ||= (t) => e.dialog = t,
			title: e.selected ? e.$pgettext("webhooks", "Edit webhook") : e.$pgettext("webhooks", "Add webhook"),
			"max-width": "640"
		}, {
			actions: p(({ close: t }) => [s(x, {
				variant: "text",
				onClick: t
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]), s(x, {
				color: "primary",
				variant: "flat",
				disabled: !e.events.length || !e.selected && !e.url.trim(),
				loading: e.saving,
				onClick: y.save
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Save")), 1)]),
				_: 1
			}, 8, [
				"disabled",
				"loading",
				"onClick"
			])]),
			default: p(() => [
				e.selected ? (l(), i("div", Y, [s(Z, {
					modelValue: e.status,
					"onUpdate:modelValue": h[3] ||= (t) => e.status = t,
					"aria-label": e.$pgettext("webhooks", "Active"),
					color: "success",
					density: "compact",
					"hide-details": "",
					class: "flex-grow-0 flex-shrink-0"
				}, null, 8, ["modelValue", "aria-label"]), a("span", X, f(e.$pgettext("webhooks", "Active")), 1)])) : r("", !0),
				e.selected ? r("", !0) : (l(), n(w, {
					key: 1,
					modelValue: e.url,
					"onUpdate:modelValue": h[4] ||= (t) => e.url = t,
					label: e.$pgettext("webhooks", "HTTPS endpoint URL"),
					variant: "underlined",
					maxlength: "500",
					autofocus: ""
				}, null, 8, ["modelValue", "label"])),
				s(T, {
					modelValue: e.events,
					"onUpdate:modelValue": h[5] ||= (t) => e.events = t,
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
				e.selected ? r("", !0) : (l(), n(Q, {
					key: 2,
					type: "info",
					variant: "tonal"
				}, {
					default: p(() => [o(f(e.$pgettext("webhooks", "New webhooks are inactive until you save them as active.")), 1)]),
					_: 1
				}))
			]),
			_: 1
		}, 8, ["modelValue", "title"]),
		s($, {
			modelValue: e.replaceDialog,
			"onUpdate:modelValue": h[8] ||= (t) => e.replaceDialog = t,
			title: e.$pgettext("webhooks", "Replace webhook destination"),
			"max-width": "640"
		}, {
			actions: p(({ close: t }) => [s(x, {
				variant: "text",
				onClick: t
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Cancel")), 1)]),
				_: 1
			}, 8, ["onClick"]), s(x, {
				color: "primary",
				variant: "flat",
				disabled: !e.url.trim(),
				loading: e.saving,
				onClick: y.replace
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Replace")), 1)]),
				_: 1
			}, 8, [
				"disabled",
				"loading",
				"onClick"
			])]),
			default: p(() => [s(w, {
				modelValue: e.url,
				"onUpdate:modelValue": h[7] ||= (t) => e.url = t,
				label: e.$pgettext("webhooks", "HTTPS endpoint URL"),
				variant: "underlined",
				maxlength: "500",
				autofocus: ""
			}, null, 8, ["modelValue", "label"]), s(Q, {
				type: "warning",
				variant: "tonal"
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Replacing the destination rotates the secret and disables the webhook.")), 1)]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue", "title"]),
		s($, {
			modelValue: e.secretDialog,
			"onUpdate:modelValue": [h[9] ||= (t) => e.secretDialog = t, h[10] ||= (e) => !e && y.closeSecret()],
			title: e.$pgettext("webhooks", "Webhook secret"),
			"max-width": "640",
			persistent: ""
		}, {
			actions: p(() => [s(x, {
				variant: "text",
				onClick: y.closeSecret
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Done")), 1)]),
				_: 1
			}, 8, ["onClick"]), s(x, {
				color: "primary",
				variant: "flat",
				onClick: y.copySecret
			}, {
				default: p(() => [o(f(e.$pgettext("webhooks", "Copy secret")), 1)]),
				_: 1
			}, 8, ["onClick"])]),
			default: p(() => [s(Q, {
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
var Q = /*#__PURE__*/ C(j, [["render", Z]]);
//#endregion
export { Q as default };
