import{$t as e,Cn as t,D as n,Hn as r,J as i,K as a,P as o,R as s,T as c,Tn as l,Ut as u,Y as d,Yt as ee,Zt as f,kt as p,nt as m,q as h,sr as g,tt as _,ur as v}from"./charts-jDwLkx1m.js";import{s as y}from"./graphql-BP2ngTk6.js";import{o as b,t as x}from"./VList-CxFXm9z9.js";import{t as S}from"./VIcon-C3jBxLcD.js";import{$ as C,At as w,Dn as T,Fn as E,H as D,Ht as O,In as k,Jn as A,Lt as j,Pn as M,Q as N,Sn as P,U as te,Vt as ne,Wn as F,b as I,gn as L,jn as R,nt as z,o as B,p as V,r as H,s as U,t as W,tr as G,tt as K,vn as q,yn as J,zt as Y}from"./index-C5owmVeV.js";import{t as X}from"./VDivider-BIS4pXp5.js";import{t as Z}from"./EditBulkDialog-DPhqe0r7.js";import{t as Q}from"./VCheckboxBtn-BSoRLOEE.js";import{t as re}from"./VTextField-Bi5EDITO.js";import{t as ie}from"./ListSort-DPkPlbQp.js";import{o as ae,r as oe}from"./files-CUlvZYTS.js";import{t as se}from"./VPagination-C_4VjcHn.js";import{t as ce}from"./SchemaItems-B9n2U6SC.js";var $=y`
  mutation ($input: ElementInput!) {
    addElement(input: $input) {
      id
      lang
      name
      type
      data
      editor
      created_at
      updated_at
      deleted_at
    }
  }
`,le=y`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,ue=y`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,de=y`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,fe=y`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,pe=y`
  mutation ($id: [ID!]!, $input: ElementInput!) {
    bulkElement(id: $id, input: $input) {
      ids
    }
  }
`,me=y`
  ${oe}
  query (
    $filter: ElementFilter
    $sort: [QueryElementsSortOrderByClause!]
    $limit: Int!
    $page: Int!
    $trashed: Trashed
    $publish: Publish
  ) {
    elements(
      filter: $filter
      sort: $sort
      first: $limit
      page: $page
      trashed: $trashed
      publish: $publish
    ) {
      data {
        id
        lang
        name
        type
        data
        editor
        created_at
        updated_at
        deleted_at
        files {
          ...CmsFileFields
        }
        latest {
          id
          published
          publish_at
          data
          editor
          created_at
          files {
            ...CmsFileFields
          }
        }
      }
      paginatorInfo {
        lastPage
      }
    }
  }
`,he=Object.freeze([{column:`ID`,order:`DESC`,label:`Latest`},{column:`ID`,order:`ASC`,label:`Oldest`},{column:`LATEST_ID`,order:`DESC`,label:`Latest edit`},{column:`LATEST_ID`,order:`ASC`,label:`Oldest edit`},{column:`NAME`,order:`ASC`,label:`Name`},{column:`TYPE`,order:`ASC`,label:`Type`},{column:`EDITOR`,order:`ASC`,label:`Editor`}]),ge={components:{SchemaItems:ce,EditBulkDialog:Z,ListSort:ie},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:[`select`],data(){return{items:[],menu:[],checked:new Set,term:``,sort:this.user.getData(`element`,`sort`)||{column:`ID`,order:`DESC`},page:1,last:1,limit:100,vschemas:!1,actions:!1,editDialog:!1,editIds:[],editSelected:!1,loading:!0,trash:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1}},setup(){let e=T();return{user:R(),changes:P(),messages:e,mdiDotsVertical:z,mdiClose:te,mdiPublish:ne,mdiDelete:N,mdiDeleteRestore:K,mdiDeleteForever:C,mdiPlus:Y,mdiMagnify:w,mdiClockOutline:D,mdiRefresh:O,mdiPencil:j,sortOptions:he,debounce:F}},created(){this.search(),this.searchd=this.debounce(this.search,500),this.embed||k(this,`element`,(e,t)=>E(this,e,t))},beforeUnmount(){this.destroyed=!0,M(this),this.items=null,this.menu=null,this.checked=null},activated(){this.sync(),this.revalidate()},computed:{canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)}},methods:{add(e){if(this.embed||!this.user.can(`element:add`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}return this.$apollo.mutate({mutation:$,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.addElement||{};return t.data=A(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(e=>{this.$log(`ElementListItems::add(): Error adding shared element`,e)})},drop(e){if(!this.user.can(`element:drop`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:le,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error trashing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::drop(): Error trashing shared element`,t,e)})},reload(){return this.outdated=!1,this.items=[],this.loading=!0,this.$apollo.provider.defaultClient.clearStore().then(()=>this.search())},revalidate(){if(this.loading)return;let e=this.options(),t=this.$apollo.provider.defaultClient.cache;if(e.fetchPolicy===`network-only`||!t.diff({query:e.query,variables:e.variables,returnPartialData:!0}).complete)return this.search()},patch(e){let t=this.items?.find(t=>t.id===e.id);if(!t)return!1;for(let n in e)n in t&&(t[n]=e[n]);return!0},patchItems(e){let t=new Map(e.map(e=>[e.id,e]));this.items?.forEach(e=>{let n=t.get(e.id);if(n)for(let t in n)t in e&&(e[t]=n[t])})},sync(){let e=this.changes.get(`element`).filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched(`element`,e)},invalidate(){q(this.$apollo.provider.defaultClient.cache,`elements`)},options(){let e=this.filter.publish||null,t=this.filter.trashed||`WITHOUT`,n={...this.filter};delete n.publish,delete n.trashed;for(let e in n)n[e]===null&&delete n[e];return this.term&&(n.any=this.term),{query:me,fetchPolicy:J(),variables:{filter:n,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}},keep(e){if(!this.user.can(`element:keep`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ue,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error restoring shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::keep(): Error restoring shared element`,t,e)})},publish(e){if(!this.user.can(`element:publish`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id)&&e.id&&!e.published);t.length&&this.$apollo.mutate({mutation:de,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error publishing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::publish(): Error publishing shared element`,t,e)})},purge(e){if(!this.user.can(`element:purge`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:fe,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error purging shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::purge(): Error purging shared element`,t,e)})},edit(e=null){this.editIds=e?[e.id]:[...this.checked],this.editSelected=!e,this.actions=!1,this.editDialog=this.editIds.length>0},save(e){if(!this.user.can(`element:save`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=this.editIds,n=this.editSelected?null:new Set(this.checked);if(!(!t.length||e===null))return this.$apollo.mutate({mutation:pe,variables:{id:t,input:{lang:e}}}).then(e=>{if(e.errors)throw e.errors;return this.editIds=[],this.editSelected&&(this.checked=new Set),this.editSelected=!1,this.invalidate(),this.search().then(()=>{n&&(this.checked=n)})}).catch(n=>{this.messages.add(this.$gettext(`Error saving shared element`)+`:
`+n,`error`),this.$log(`ElementListItems::save(): Error saving shared elements`,t,e,n)})},search(){return this.user.can(`element:view`)?(this.loading=!0,this.$apollo.query(this.options()).then(e=>{if(e.errors)throw e.errors;let t=e.data.elements||{};return this.last=t.paginatorInfo?.lastPage||1,this.items=[...t.data||[]].map(e=>{let t=e.latest,n=t?.data?G(t.data):{...e,data:G(e.data)};return n.data&&typeof n.data==`object`&&(n.data=r(n.data)),Object.assign(n,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null,files:Object.freeze((t?.files||e.files||[]).map(ae))})}),this.checked=new Set,this.outdated=!1,this.loading=!1,this.items}).catch(e=>{this.messages.add(this.$gettext(`Error fetching shared elements`)+`:
`+e,`error`),this.$log(`ElementListItems::search(): Error fetching shared element`,e)})):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))},title(e){let t=[];return e.publish_at&&t.push(`Publish at: `+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked=this.checked.size>0?new Set:new Set(this.items.map(e=>e.id))},toggleCheck(e){let t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData(`element`,`sort`,this.sort),this.search()}}}},_e={class:`header`},ve={class:`bulk`},ye={class:`btn-actions`},be={class:`search`},xe={class:`layout`},Se={class:`actions`},Ce={class:`btn-actions`},we=[`onClick`,`title`],Te={class:`item-text`},Ee={class:`item-head`},De={key:0,class:`item-lang`},Oe={class:`item-title`},ke={class:`item-type item-subtitle`},Ae={class:`item-aux`},je={class:`item-editor`},Me={class:`item-modified item-subtitle`},Ne={key:0,class:`loading`},Pe={key:1,class:`notfound`},Fe={key:3,class:`btn-group`};function Ie(r,y,C,w,T,E){let D=f(`ListSort`),O=f(`SchemaItems`),k=f(`EditBulkDialog`);return u(),d(o,null,[a(`div`,_e,[a(`div`,ve,[m(Q,{"model-value":T.checked.size>0,onClick:y[0]||=n(e=>E.toggle(),[`stop`]),"aria-label":r.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),a(`span`,ye,[(u(),h(e(r.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":r.$gettext(`Actions`),modelValue:T.actions,"onUpdate:modelValue":y[8]||=e=>T.actions=e,transition:`scale-transition`,location:`end center`,"max-width":`300`},{activator:t(({props:e})=>[m(V,p(e,{disabled:!E.isChecked||C.embed||!w.user.can(`element:add`),title:r.$gettext(`Actions`),icon:w.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:t(()=>[m(B,null,{default:t(()=>[m(W,{density:`compact`},{default:t(()=>[m(H,null,{default:t(()=>[_(v(r.$gettext(`Actions`)),1)]),_:1}),m(V,{icon:w.mdiClose,"aria-label":r.$gettext(`Close`),onClick:y[1]||=e=>T.actions=!1},null,8,[`icon`,`aria-label`])]),_:1}),m(x,{onClick:y[7]||=e=>T.actions=!1},{default:t(()=>[l(m(b,null,{default:t(()=>[m(V,{"prepend-icon":w.mdiPublish,variant:`text`,onClick:y[2]||=e=>E.publish()},{default:t(()=>[_(v(r.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,E.isChecked&&w.user.can(`element:publish`)]]),l(m(b,null,{default:t(()=>[m(V,{"prepend-icon":w.mdiPencil,variant:`text`,onClick:y[3]||=e=>E.edit()},{default:t(()=>[_(v(r.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,E.isChecked&&w.user.can(`element:save`)]]),l(m(b,null,{default:t(()=>[m(V,{"prepend-icon":w.mdiDelete,variant:`text`,onClick:y[4]||=e=>E.drop()},{default:t(()=>[_(v(r.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,E.canTrash&&w.user.can(`element:drop`)]]),l(m(b,null,{default:t(()=>[m(V,{"prepend-icon":w.mdiDeleteRestore,variant:`text`,onClick:y[5]||=e=>E.keep()},{default:t(()=>[_(v(r.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,E.isTrashed&&w.user.can(`element:keep`)]]),l(m(b,null,{default:t(()=>[m(V,{"prepend-icon":w.mdiDeleteForever,variant:`text`,onClick:y[6]||=e=>E.purge()},{default:t(()=>[_(v(r.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,E.isChecked&&w.user.can(`element:purge`)]])]),_:1})]),_:1})]),_:1},8,[`aria-label`,`modelValue`]))]),!this.embed&&this.user.can(`element:add`)?(u(),h(V,{key:0,onClick:y[9]||=e=>T.vschemas=!0,title:r.$gettext(`Add element`),disabled:T.loading,icon:w.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):i(``,!0)]),a(`div`,be,[m(re,{modelValue:T.term,"onUpdate:modelValue":y[10]||=e=>T.term=e,"prepend-inner-icon":w.mdiMagnify,variant:`underlined`,label:r.$gettext(`Search for`),"hide-details":``,clearable:``},null,8,[`modelValue`,`prepend-inner-icon`,`label`])]),a(`div`,xe,[T.outdated?(u(),h(V,{key:0,onClick:y[11]||=e=>E.reload(),"prepend-icon":w.mdiRefresh,title:r.$gettext(`Updated by another user`),color:`primary`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:t(()=>[_(v(r.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):i(``,!0),m(V,{onClick:y[12]||=e=>E.reload(),title:r.$gettext(`Reload elements`),icon:w.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`title`,`icon`]),m(D,{modelValue:T.sort,"onUpdate:modelValue":y[13]||=e=>T.sort=e,options:w.sortOptions},null,8,[`modelValue`,`options`])])]),m(x,{class:`items`},{default:t(()=>[(u(!0),d(o,null,ee(T.items,(o,s)=>(u(),h(b,{key:s},{default:t(()=>[a(`div`,Se,[m(Q,{"model-value":T.checked.has(o.id),"onUpdate:modelValue":e=>E.toggleCheck(o),class:g([{draft:!o.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),a(`span`,Ce,[(u(),h(e(r.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":r.$gettext(`Actions`),modelValue:T.menu[s],"onUpdate:modelValue":e=>T.menu[s]=e,transition:`scale-transition`,location:`end center`,"max-width":`300`},{activator:t(({props:e})=>[m(V,p({ref_for:!0},e,{title:r.$gettext(`Actions`),icon:w.mdiDotsVertical,variant:`text`}),null,16,[`title`,`icon`])]),default:t(()=>[m(B,null,{default:t(()=>[m(W,{density:`compact`},{default:t(()=>[m(H,null,{default:t(()=>[_(v(r.$gettext(`Actions`)),1)]),_:1}),m(V,{icon:w.mdiClose,"aria-label":r.$gettext(`Close`),onClick:e=>T.menu[s]=!1},null,8,[`icon`,`aria-label`,`onClick`])]),_:2},1024),m(x,{onClick:e=>T.menu[s]=!1},{default:t(()=>[l(m(b,null,{default:t(()=>[m(V,{"prepend-icon":w.mdiPublish,variant:`text`,onClick:e=>E.publish(o)},{default:t(()=>[_(v(r.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1536),[[c,!o.deleted_at&&!o.published&&this.user.can(`element:publish`)]]),!o.deleted_at&&!o.published&&w.user.can(`element:publish`)&&w.user.can(`element:save`)?(u(),h(X,{key:0})):i(``,!0),w.user.can(`element:save`)?(u(),h(b,{key:1},{default:t(()=>[m(V,{"prepend-icon":w.mdiPencil,variant:`text`,onClick:e=>E.edit(o)},{default:t(()=>[_(v(r.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),w.user.can(`element:save`)?(u(),h(X,{key:2})):i(``,!0),!o.deleted_at&&this.user.can(`element:drop`)?(u(),h(b,{key:3},{default:t(()=>[m(V,{"prepend-icon":w.mdiDelete,variant:`text`,onClick:e=>E.drop(o)},{default:t(()=>[_(v(r.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),o.deleted_at&&this.user.can(`element:keep`)?(u(),h(b,{key:4},{default:t(()=>[m(V,{"prepend-icon":w.mdiDeleteRestore,variant:`text`,onClick:e=>E.keep(o)},{default:t(()=>[_(v(r.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),this.user.can(`element:purge`)?(u(),h(b,{key:5},{default:t(()=>[m(V,{"prepend-icon":w.mdiDeleteForever,variant:`text`,onClick:e=>E.purge(o)},{default:t(()=>[_(v(r.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0)]),_:2},1032,[`onClick`])]),_:2},1024)]),_:2},1032,[`aria-label`,`modelValue`,`onUpdate:modelValue`]))])]),a(`a`,{href:`#`,class:g([`item-content`,{trashed:o.deleted_at}]),onClick:n(e=>r.$emit(`select`,o),[`prevent`]),title:E.title(o)},[a(`div`,Te,[a(`div`,Ee,[o.lang?(u(),d(`span`,De,v(o.lang),1)):i(``,!0),o.publish_at?(u(),h(S,{key:1,class:`publish-at`,icon:w.mdiClockOutline},null,8,[`icon`])):i(``,!0),a(`span`,Oe,v(o.name||r.$gettext(`New`)),1)]),a(`div`,ke,v(o.type),1)]),a(`div`,Ae,[a(`div`,je,v(o.editor),1),a(`div`,Me,v(new Date(o.updated_at).toLocaleString()),1)])],10,we)]),_:2},1024))),128))]),_:1}),T.loading?(u(),d(`p`,Ne,[_(v(r.$gettext(`Loading`))+` `,1),y[20]||=a(`svg`,{class:`spinner`,width:`32`,height:`32`,fill:`currentColor`,viewBox:`0 0 24 24`,xmlns:`http://www.w3.org/2000/svg`},[a(`circle`,{class:`spin1`,cx:`4`,cy:`12`,r:`3`}),a(`circle`,{class:`spin1 spin2`,cx:`12`,cy:`12`,r:`3`}),a(`circle`,{class:`spin1 spin3`,cx:`20`,cy:`12`,r:`3`})],-1)])):i(``,!0),!T.loading&&!T.items.length?(u(),d(`p`,Pe,v(r.$gettext(`No entries found`)),1)):i(``,!0),T.last>1?(u(),h(se,{key:2,modelValue:T.page,"onUpdate:modelValue":y[14]||=e=>T.page=e,length:T.last},null,8,[`modelValue`,`length`])):i(``,!0),!this.embed&&this.user.can(`element:add`)?(u(),d(`div`,Fe,[m(V,{onClick:y[15]||=e=>T.vschemas=!0,title:r.$gettext(`Add element`),disabled:T.loading,icon:w.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):i(``,!0),(u(),h(s,{to:`body`},[m(L,{modelValue:T.vschemas,"onUpdate:modelValue":y[17]||=e=>T.vschemas=e,onAfterLeave:y[18]||=e=>T.vschemas=!1,scrollable:``,width:`auto`},{default:t(()=>[m(B,null,{default:t(()=>[m(U,null,{default:t(()=>[m(O,{type:`content`,onAdd:y[16]||=e=>E.add(e)})]),_:1})]),_:1})]),_:1},8,[`modelValue`])])),m(k,{modelValue:T.editDialog,"onUpdate:modelValue":y[19]||=e=>T.editDialog=e,count:T.editIds.length,onApply:E.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var Le=I(ge,[[`render`,Ie],[`__scopeId`,`data-v-2703f488`]]);export{Le as t};