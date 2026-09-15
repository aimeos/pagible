import e from "graphql-tag";
import { Fragment as t, createBlock as n, createCommentVNode as r, createElementBlock as i, createElementVNode as a, createTextVNode as o, createVNode as s, openBlock as c, renderList as l, resolveComponent as u, toDisplayString as d, withCtx as f, withModifiers as p } from "vue";
//#region \0plugin-vue:export-helper
var m = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, h = e`
  fragment CmsWebhookFields on CmsWebhook {
    id
    status
    failures
    endpoint
    events
    last_error
    last_success_at
  }
`, g = e`
  query CmsWebhooks {
    cmsWebhooks {
      ...CmsWebhookFields
    }
    cmsWebhookEvents
  }
  ${h}
`, _ = e`
  mutation AddWebhook($input: CmsWebhookAddInput!) {
    addWebhook(input: $input) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${h}
`, v = e`
  mutation SaveWebhook($id: ID!, $input: CmsWebhookSaveInput!) {
    saveWebhook(id: $id, input: $input) {
      ...CmsWebhookFields
    }
  }
  ${h}
`, y = e`
  mutation ReplaceWebhook($id: ID!, $url: String!) {
    replaceWebhook(id: $id, url: $url) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${h}
`, b = e`
  mutation RotateWebhook($id: ID!) {
    rotateWebhook(id: $id) {
      secret
      webhook {
        ...CmsWebhookFields
      }
    }
  }
  ${h}
`, x = e`
  mutation DropWebhook($id: [ID!]!) {
    dropWebhook(id: $id)
  }
`, S = {
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
		url: "",
		events: [],
		status: !1,
		secret: ""
	}),
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
					query: g,
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
						mutation: v,
						variables: {
							id: this.selected.id,
							input: {
								events: this.events,
								status: this.status
							}
						}
					});
					this.replaceItem(e.saveWebhook);
				} else {
					let { data: e } = await this.apollo.mutate({
						mutation: _,
						variables: { input: {
							url: this.url.trim(),
							events: this.events
						} }
					});
					this.items.unshift(e.addWebhook.webhook), this.showSecret(e.addWebhook.secret);
				}
				this.dialog = !1;
			}, this.$gettext("Error saving webhook"));
		},
		async replace() {
			this.selected && this.url.trim() && await this.change(async () => {
				let { data: e } = await this.apollo.mutate({
					mutation: y,
					variables: {
						id: this.selected.id,
						url: this.url.trim()
					}
				});
				this.replaceItem(e.replaceWebhook.webhook), this.replaceDialog = !1, this.showSecret(e.replaceWebhook.secret);
			}, this.$gettext("Error replacing webhook destination"));
		},
		async rotate(e) {
			await this.change(async () => {
				let { data: t } = await this.apollo.mutate({
					mutation: b,
					variables: { id: e.id }
				});
				this.replaceItem(t.rotateWebhook.webhook), this.showSecret(t.rotateWebhook.secret);
			}, this.$gettext("Error rotating webhook secret"));
		},
		async remove(e = null) {
			let t = e ? [e.id] : [...this.checked], n = e ? this.$gettext("Delete this webhook?") : `${this.$gettext("Delete")} (${t.length})?`;
			!this.saving && t.length && window.confirm(n) && await this.change(async () => {
				await this.apollo.mutate({
					mutation: x,
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
			this.checked = this.checked.size ? /* @__PURE__ */ new Set() : new Set(this.items.map((e) => e.id));
		},
		toggleCheck(e) {
			let t = new Set(this.checked);
			t.has(e.id) ? t.delete(e.id) : t.add(e.id), this.checked = t;
		},
		replaceItem(e) {
			let t = this.items.findIndex((t) => t.id === e.id);
			t >= 0 && this.items.splice(t, 1, e);
		},
		showSecret(e) {
			this.secret = e, this.secretDialog = !0;
		}
	}
}, C = { class: "webhook-list" }, w = { class: "d-flex align-center ga-3 mb-5" }, T = { class: "text-medium-emphasis mb-0" }, E = { class: "text-end" }, D = { class: "text-end text-no-wrap" };
function O(e, m, h, g, _, v) {
	let y = u("v-spacer"), b = u("v-btn"), x = u("v-progress-linear"), S = u("v-alert"), O = u("v-checkbox-btn"), k = u("v-chip"), A = u("v-table"), j = u("v-container"), M = u("v-card-title"), N = u("v-text-field"), P = u("v-select"), F = u("v-switch"), I = u("v-card-text"), L = u("v-card-actions"), R = u("v-card"), z = u("v-dialog");
	return c(), i("div", C, [
		s(j, {
			fluid: "",
			class: "pa-4 pa-md-6"
		}, {
			default: f(() => [a("div", w, [
				a("p", T, d(e.$gettext("Send signed notifications when published content changes.")), 1),
				s(y),
				e.checked.size ? (c(), n(b, {
					key: 0,
					color: "error",
					variant: "text",
					disabled: e.saving,
					onClick: m[0] ||= (e) => v.remove()
				}, {
					default: f(() => [o(d(e.$gettext("Delete")) + " (" + d(e.checked.size) + ") ", 1)]),
					_: 1
				}, 8, ["disabled"])) : r("", !0),
				s(b, {
					color: "primary",
					onClick: v.openAdd
				}, {
					default: f(() => [o(d(e.$gettext("Add webhook")), 1)]),
					_: 1
				}, 8, ["onClick"])
			]), e.loading ? (c(), n(x, {
				key: 0,
				indeterminate: ""
			})) : e.items.length ? (c(), n(A, { key: 2 }, {
				default: f(() => [a("thead", null, [a("tr", null, [
					a("th", null, [s(O, {
						"model-value": e.checked.size > 0,
						onClick: p(v.toggle, ["stop"]),
						"aria-label": e.$gettext("Toggle selection")
					}, null, 8, [
						"model-value",
						"onClick",
						"aria-label"
					])]),
					a("th", null, d(e.$gettext("Endpoint")), 1),
					a("th", null, d(e.$gettext("Events")), 1),
					a("th", null, d(e.$gettext("Status")), 1),
					a("th", null, d(e.$gettext("Failures")), 1),
					a("th", null, d(e.$gettext("Last success")), 1),
					a("th", null, d(e.$gettext("Last error")), 1),
					a("th", E, d(e.$gettext("Actions")), 1)
				])]), a("tbody", null, [(c(!0), i(t, null, l(e.items, (t) => (c(), i("tr", { key: t.id }, [
					a("td", null, [s(O, {
						"model-value": e.checked.has(t.id),
						"onUpdate:modelValue": (e) => v.toggleCheck(t),
						"aria-label": e.$gettext("Toggle selection")
					}, null, 8, [
						"model-value",
						"onUpdate:modelValue",
						"aria-label"
					])]),
					a("td", null, d(t.endpoint), 1),
					a("td", null, d(t.events.join(", ")), 1),
					a("td", null, [s(k, {
						color: t.status ? "success" : void 0,
						size: "small"
					}, {
						default: f(() => [o(d(t.status ? e.$gettext("Active") : e.$gettext("Inactive")), 1)]),
						_: 2
					}, 1032, ["color"])]),
					a("td", null, d(t.failures), 1),
					a("td", null, d(v.successText(t)), 1),
					a("td", null, d(v.errorText(t)), 1),
					a("td", D, [
						s(b, {
							variant: "text",
							size: "small",
							onClick: (e) => v.openEdit(t)
						}, {
							default: f(() => [o(d(e.$gettext("Edit")), 1)]),
							_: 1
						}, 8, ["onClick"]),
						s(b, {
							variant: "text",
							size: "small",
							onClick: (e) => v.openReplace(t)
						}, {
							default: f(() => [o(d(e.$gettext("Replace")), 1)]),
							_: 1
						}, 8, ["onClick"]),
						s(b, {
							variant: "text",
							size: "small",
							onClick: (e) => v.rotate(t)
						}, {
							default: f(() => [o(d(e.$gettext("Rotate")), 1)]),
							_: 1
						}, 8, ["onClick"]),
						s(b, {
							variant: "text",
							size: "small",
							color: "error",
							onClick: (e) => v.remove(t)
						}, {
							default: f(() => [o(d(e.$gettext("Delete")), 1)]),
							_: 1
						}, 8, ["onClick"])
					])
				]))), 128))])]),
				_: 1
			})) : (c(), n(S, {
				key: 1,
				type: "info",
				variant: "tonal"
			}, {
				default: f(() => [o(d(e.$gettext("No webhooks configured.")), 1)]),
				_: 1
			}))]),
			_: 1
		}),
		s(z, {
			modelValue: e.dialog,
			"onUpdate:modelValue": m[5] ||= (t) => e.dialog = t,
			"max-width": "640"
		}, {
			default: f(() => [s(R, null, {
				default: f(() => [
					s(M, null, {
						default: f(() => [o(d(e.selected ? e.$gettext("Edit webhook") : e.$gettext("Add webhook")), 1)]),
						_: 1
					}),
					s(I, null, {
						default: f(() => [
							e.selected ? r("", !0) : (c(), n(N, {
								key: 0,
								modelValue: e.url,
								"onUpdate:modelValue": m[1] ||= (t) => e.url = t,
								label: e.$gettext("HTTPS endpoint URL"),
								maxlength: "500",
								autofocus: ""
							}, null, 8, ["modelValue", "label"])),
							s(P, {
								modelValue: e.events,
								"onUpdate:modelValue": m[2] ||= (t) => e.events = t,
								items: e.names,
								label: e.$gettext("Events"),
								multiple: "",
								chips: ""
							}, null, 8, [
								"modelValue",
								"items",
								"label"
							]),
							e.selected ? (c(), n(F, {
								key: 1,
								modelValue: e.status,
								"onUpdate:modelValue": m[3] ||= (t) => e.status = t,
								color: "success",
								label: e.$gettext("Active")
							}, null, 8, ["modelValue", "label"])) : (c(), n(S, {
								key: 2,
								type: "info",
								variant: "tonal"
							}, {
								default: f(() => [o(d(e.$gettext("New webhooks are inactive until you save them as active.")), 1)]),
								_: 1
							}))
						]),
						_: 1
					}),
					s(L, null, {
						default: f(() => [
							s(y),
							s(b, { onClick: m[4] ||= (t) => e.dialog = !1 }, {
								default: f(() => [o(d(e.$gettext("Cancel")), 1)]),
								_: 1
							}),
							s(b, {
								color: "primary",
								loading: e.saving,
								onClick: v.save
							}, {
								default: f(() => [o(d(e.$gettext("Save")), 1)]),
								_: 1
							}, 8, ["loading", "onClick"])
						]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"]),
		s(z, {
			modelValue: e.replaceDialog,
			"onUpdate:modelValue": m[8] ||= (t) => e.replaceDialog = t,
			"max-width": "640"
		}, {
			default: f(() => [s(R, null, {
				default: f(() => [
					s(M, null, {
						default: f(() => [o(d(e.$gettext("Replace webhook destination")), 1)]),
						_: 1
					}),
					s(I, null, {
						default: f(() => [s(N, {
							modelValue: e.url,
							"onUpdate:modelValue": m[6] ||= (t) => e.url = t,
							label: e.$gettext("HTTPS endpoint URL"),
							maxlength: "500",
							autofocus: ""
						}, null, 8, ["modelValue", "label"]), s(S, {
							type: "warning",
							variant: "tonal"
						}, {
							default: f(() => [o(d(e.$gettext("Replacing the destination rotates the secret and disables the webhook.")), 1)]),
							_: 1
						})]),
						_: 1
					}),
					s(L, null, {
						default: f(() => [
							s(y),
							s(b, { onClick: m[7] ||= (t) => e.replaceDialog = !1 }, {
								default: f(() => [o(d(e.$gettext("Cancel")), 1)]),
								_: 1
							}),
							s(b, {
								color: "primary",
								loading: e.saving,
								onClick: v.replace
							}, {
								default: f(() => [o(d(e.$gettext("Replace")), 1)]),
								_: 1
							}, 8, ["loading", "onClick"])
						]),
						_: 1
					})
				]),
				_: 1
			})]),
			_: 1
		}, 8, ["modelValue"]),
		s(z, {
			modelValue: e.secretDialog,
			"onUpdate:modelValue": m[10] ||= (t) => e.secretDialog = t,
			"max-width": "640",
			persistent: ""
		}, {
			default: f(() => [s(R, null, {
				default: f(() => [
					s(M, null, {
						default: f(() => [o(d(e.$gettext("Webhook secret")), 1)]),
						_: 1
					}),
					s(I, null, {
						default: f(() => [s(S, {
							type: "warning",
							variant: "tonal",
							class: "mb-4"
						}, {
							default: f(() => [o(d(e.$gettext("Copy this secret now. It will not be shown again.")), 1)]),
							_: 1
						}), s(N, {
							"model-value": e.secret,
							readonly: ""
						}, null, 8, ["model-value"])]),
						_: 1
					}),
					s(L, null, {
						default: f(() => [
							s(b, {
								color: "primary",
								onClick: v.copySecret
							}, {
								default: f(() => [o(d(e.$gettext("Copy secret")), 1)]),
								_: 1
							}, 8, ["onClick"]),
							s(y),
							s(b, { onClick: m[9] ||= (t) => {
								e.secretDialog = !1, e.secret = "";
							} }, {
								default: f(() => [o(d(e.$gettext("Done")), 1)]),
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
	]);
}
var k = /*#__PURE__*/ m(S, [["render", O]]);
//#endregion
export { k as default };
