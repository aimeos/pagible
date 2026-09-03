import{$t as e,Cn as t,D as n,Hn as r,J as i,K as a,P as o,R as s,T as c,Tn as l,Ut as u,Y as d,Yt as f,Zt as p,kt as m,nt as h,q as g,sr as _,tt as v,ur as y}from"./charts-jDwLkx1m.js";import{s as b}from"./graphql-BP2ngTk6.js";import{o as x,t as S}from"./VList-DMd4Cl1y.js";import{t as C}from"./VIcon-C3jBxLcD.js";import{$ as w,$n as T,At as E,Dn as D,Fn as O,H as k,Hn as A,Ht as j,In as M,Kn as N,Lt as ee,Pn as te,Q as ne,Sn as P,U as F,Vt as I,b as L,gn as R,jn as z,m as B,nt as V,o as H,r as U,s as W,t as G,tt as K,vn as q,yn as J,zt as Y}from"./index-C-CmC1EW.js";import{t as X}from"./VDivider-BIS4pXp5.js";import{t as Z}from"./EditBulkDialog-DYO7bsCK.js";import{t as Q}from"./VCheckboxBtn-FdYxKJWa.js";import{t as re}from"./VTextField-B8RMmzS_.js";import{t as ie}from"./ListSort-BpSTpkiB.js";import{a as ae,r as oe}from"./files-DCdObwaI.js";import{t as se}from"./VPagination-B-6cXwyh.js";import{t as ce}from"./SchemaItems-CcD62TW9.js";var le=b`
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
`,ue=b`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,de=b`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,fe=b`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,pe=b`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,me=b`
  mutation ($id: [ID!]!, $input: ElementInput!) {
    bulkElement(id: $id, input: $input) {
      ids
    }
  }
`,he=b`
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
`,ge=Object.freeze([{column:`ID`,order:`DESC`,label:`Latest`},{column:`ID`,order:`ASC`,label:`Oldest`},{column:`LATEST_ID`,order:`DESC`,label:`Latest edit`},{column:`LATEST_ID`,order:`ASC`,label:`Oldest edit`},{column:`NAME`,order:`ASC`,label:`Name`},{column:`TYPE`,order:`ASC`,label:`Type`},{column:`EDITOR`,order:`ASC`,label:`Editor`}]),_e={components:{SchemaItems:ce,EditBulkDialog:Z,ListSort:ie},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:[`select`],data(){return{items:[],menu:[],checked:new Set,term:``,sort:this.user.getData(`element`,`sort`)||{column:`ID`,order:`DESC`},page:1,last:1,limit:100,vschemas:!1,actions:!1,editDialog:!1,editIds:[],editSelected:!1,loading:!0,trash:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1}},setup(){let e=D();return{user:z(),changes:P(),messages:e,mdiDotsVertical:V,mdiClose:F,mdiPublish:I,mdiDelete:ne,mdiDeleteRestore:K,mdiDeleteForever:w,mdiPlus:Y,mdiMagnify:E,mdiClockOutline:k,mdiRefresh:j,mdiPencil:ee,sortOptions:ge,debounce:A}},created(){this.search(),this.searchd=this.debounce(this.search,500),this.embed||M(this,`element`,(e,t)=>O(this,e,t))},beforeUnmount(){this.destroyed=!0,te(this),this.items=null,this.menu=null,this.checked=null},activated(){this.sync(),this.revalidate()},computed:{canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)}},methods:{add(e){if(this.embed||!this.user.can(`element:add`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}return this.$apollo.mutate({mutation:le,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.addElement||{};return t.data=N(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(e=>{this.$log(`ElementListItems::add(): Error adding shared element`,e)})},drop(e){if(!this.user.can(`element:drop`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ue,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error trashing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::drop(): Error trashing shared element`,t,e)})},reload(){return this.outdated=!1,this.items=[],this.loading=!0,this.$apollo.provider.defaultClient.clearStore().then(()=>this.search())},revalidate(){if(this.loading)return;let e=this.options(),t=this.$apollo.provider.defaultClient.cache;if(e.fetchPolicy===`network-only`||!t.diff({query:e.query,variables:e.variables,returnPartialData:!0}).complete)return this.search()},patch(e){let t=this.items?.find(t=>t.id===e.id);if(!t)return!1;for(let n in e)n in t&&(t[n]=e[n]);return!0},patchItems(e){let t=new Map(e.map(e=>[e.id,e]));this.items?.forEach(e=>{let n=t.get(e.id);if(n)for(let t in n)t in e&&(e[t]=n[t])})},sync(){let e=this.changes.get(`element`).filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched(`element`,e)},invalidate(){q(this.$apollo.provider.defaultClient.cache,`elements`)},options(){let e=this.filter.publish||null,t=this.filter.trashed||`WITHOUT`,n={...this.filter};delete n.publish,delete n.trashed;for(let e in n)n[e]===null&&delete n[e];return this.term&&(n.any=this.term),{query:he,fetchPolicy:J(),variables:{filter:n,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}},keep(e){if(!this.user.can(`element:keep`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:de,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error restoring shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::keep(): Error restoring shared element`,t,e)})},publish(e){if(!this.user.can(`element:publish`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id)&&e.id&&!e.published);t.length&&this.$apollo.mutate({mutation:fe,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error publishing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::publish(): Error publishing shared element`,t,e)})},purge(e){if(!this.user.can(`element:purge`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:pe,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error purging shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::purge(): Error purging shared element`,t,e)})},edit(e=null){this.editIds=e?[e.id]:[...this.checked],this.editSelected=!e,this.actions=!1,this.editDialog=this.editIds.length>0},save(e){if(!this.user.can(`element:save`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=this.editIds,n=this.editSelected?null:new Set(this.checked);if(!(!t.length||e===null))return this.$apollo.mutate({mutation:me,variables:{id:t,input:{lang:e}}}).then(e=>{if(e.errors)throw e.errors;return this.editIds=[],this.editSelected&&(this.checked=new Set),this.editSelected=!1,this.invalidate(),this.search().then(()=>{n&&(this.checked=n)})}).catch(n=>{this.messages.add(this.$gettext(`Error saving shared element`)+`:
`+n,`error`),this.$log(`ElementListItems::save(): Error saving shared elements`,t,e,n)})},search(){return this.user.can(`element:view`)?(this.loading=!0,this.$apollo.query(this.options()).then(e=>{if(e.errors)throw e.errors;let t=e.data.elements||{};return this.last=t.paginatorInfo?.lastPage||1,this.items=[...t.data||[]].map(e=>{let t=e.latest,n=t?.data?T(t.data):{...e,data:T(e.data)};return n.data&&typeof n.data==`object`&&(n.data=r(n.data)),Object.assign(n,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null,files:Object.freeze((t?.files||e.files||[]).map(ae))})}),this.checked=new Set,this.outdated=!1,this.loading=!1,this.items}).catch(e=>{this.messages.add(this.$gettext(`Error fetching shared elements`)+`:
`+e,`error`),this.$log(`ElementListItems::search(): Error fetching shared element`,e)})):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))},title(e){let t=[];return e.publish_at&&t.push(`Publish at: `+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked=this.checked.size>0?new Set:new Set(this.items.map(e=>e.id))},toggleCheck(e){let t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData(`element`,`sort`,this.sort),this.search()}}}},ve={class:`header`},ye={class:`bulk`},be={class:`btn-actions`},xe={class:`search`},Se={class:`layout`},Ce={class:`actions`},we={class:`btn-actions`},Te=[`onClick`,`title`],Ee={class:`item-text`},De={class:`item-head`},$={key:0,class:`item-lang`},Oe={class:`item-title`},ke={class:`item-type item-subtitle`},Ae={class:`item-aux`},je={class:`item-editor`},Me={class:`item-modified item-subtitle`},Ne={key:0,class:`loading`},Pe={key:1,class:`notfound`},Fe={key:3,class:`btn-group`};function Ie(r,b,w,T,E,D){let O=p(`ListSort`),k=p(`SchemaItems`),A=p(`EditBulkDialog`);return u(),d(o,null,[a(`div`,ve,[a(`div`,ye,[h(Q,{"model-value":E.checked.size>0,onClick:b[0]||=n(e=>D.toggle(),[`stop`]),"aria-label":r.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),a(`span`,be,[(u(),g(e(r.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":r.$gettext(`Actions`),modelValue:E.actions,"onUpdate:modelValue":b[8]||=e=>E.actions=e,transition:`scale-transition`,location:`end center`,"max-width":`300`},{activator:t(({props:e})=>[h(B,m(e,{disabled:!D.isChecked||w.embed||!T.user.can(`element:add`),title:r.$gettext(`Actions`),icon:T.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:t(()=>[h(H,null,{default:t(()=>[h(G,{density:`compact`},{default:t(()=>[h(U,null,{default:t(()=>[v(y(r.$gettext(`Actions`)),1)]),_:1}),h(B,{icon:T.mdiClose,"aria-label":r.$gettext(`Close`),onClick:b[1]||=e=>E.actions=!1},null,8,[`icon`,`aria-label`])]),_:1}),h(S,{onClick:b[7]||=e=>E.actions=!1},{default:t(()=>[l(h(x,null,{default:t(()=>[h(B,{"prepend-icon":T.mdiPublish,variant:`text`,onClick:b[2]||=e=>D.publish()},{default:t(()=>[v(y(r.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,D.isChecked&&T.user.can(`element:publish`)]]),l(h(x,null,{default:t(()=>[h(B,{"prepend-icon":T.mdiPencil,variant:`text`,onClick:b[3]||=e=>D.edit()},{default:t(()=>[v(y(r.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,D.isChecked&&T.user.can(`element:save`)]]),l(h(x,null,{default:t(()=>[h(B,{"prepend-icon":T.mdiDelete,variant:`text`,onClick:b[4]||=e=>D.drop()},{default:t(()=>[v(y(r.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,D.canTrash&&T.user.can(`element:drop`)]]),l(h(x,null,{default:t(()=>[h(B,{"prepend-icon":T.mdiDeleteRestore,variant:`text`,onClick:b[5]||=e=>D.keep()},{default:t(()=>[v(y(r.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,D.isTrashed&&T.user.can(`element:keep`)]]),l(h(x,null,{default:t(()=>[h(B,{"prepend-icon":T.mdiDeleteForever,variant:`text`,onClick:b[6]||=e=>D.purge()},{default:t(()=>[v(y(r.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[c,D.isChecked&&T.user.can(`element:purge`)]])]),_:1})]),_:1})]),_:1},8,[`aria-label`,`modelValue`]))]),!this.embed&&this.user.can(`element:add`)?(u(),g(B,{key:0,onClick:b[9]||=e=>E.vschemas=!0,title:r.$gettext(`Add element`),disabled:E.loading,icon:T.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):i(``,!0)]),a(`div`,xe,[h(re,{modelValue:E.term,"onUpdate:modelValue":b[10]||=e=>E.term=e,"prepend-inner-icon":T.mdiMagnify,variant:`underlined`,label:r.$gettext(`Search for`),"hide-details":``,clearable:``},null,8,[`modelValue`,`prepend-inner-icon`,`label`])]),a(`div`,Se,[E.outdated?(u(),g(B,{key:0,onClick:b[11]||=e=>D.reload(),"prepend-icon":T.mdiRefresh,title:r.$gettext(`Updated by another user`),color:`primary`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:t(()=>[v(y(r.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):i(``,!0),h(B,{onClick:b[12]||=e=>D.reload(),title:r.$gettext(`Reload elements`),icon:T.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`title`,`icon`]),h(O,{modelValue:E.sort,"onUpdate:modelValue":b[13]||=e=>E.sort=e,options:T.sortOptions},null,8,[`modelValue`,`options`])])]),h(S,{class:`items`},{default:t(()=>[(u(!0),d(o,null,f(E.items,(o,s)=>(u(),g(x,{key:s},{default:t(()=>[a(`div`,Ce,[h(Q,{"model-value":E.checked.has(o.id),"onUpdate:modelValue":e=>D.toggleCheck(o),class:_([{draft:!o.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),a(`span`,we,[(u(),g(e(r.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":r.$gettext(`Actions`),modelValue:E.menu[s],"onUpdate:modelValue":e=>E.menu[s]=e,transition:`scale-transition`,location:`end center`,"max-width":`300`},{activator:t(({props:e})=>[h(B,m({ref_for:!0},e,{title:r.$gettext(`Actions`),icon:T.mdiDotsVertical,variant:`text`}),null,16,[`title`,`icon`])]),default:t(()=>[h(H,null,{default:t(()=>[h(G,{density:`compact`},{default:t(()=>[h(U,null,{default:t(()=>[v(y(r.$gettext(`Actions`)),1)]),_:1}),h(B,{icon:T.mdiClose,"aria-label":r.$gettext(`Close`),onClick:e=>E.menu[s]=!1},null,8,[`icon`,`aria-label`,`onClick`])]),_:2},1024),h(S,{onClick:e=>E.menu[s]=!1},{default:t(()=>[l(h(x,null,{default:t(()=>[h(B,{"prepend-icon":T.mdiPublish,variant:`text`,onClick:e=>D.publish(o)},{default:t(()=>[v(y(r.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1536),[[c,!o.deleted_at&&!o.published&&this.user.can(`element:publish`)]]),!o.deleted_at&&!o.published&&T.user.can(`element:publish`)&&T.user.can(`element:save`)?(u(),g(X,{key:0})):i(``,!0),T.user.can(`element:save`)?(u(),g(x,{key:1},{default:t(()=>[h(B,{"prepend-icon":T.mdiPencil,variant:`text`,onClick:e=>D.edit(o)},{default:t(()=>[v(y(r.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),T.user.can(`element:save`)?(u(),g(X,{key:2})):i(``,!0),!o.deleted_at&&this.user.can(`element:drop`)?(u(),g(x,{key:3},{default:t(()=>[h(B,{"prepend-icon":T.mdiDelete,variant:`text`,onClick:e=>D.drop(o)},{default:t(()=>[v(y(r.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),o.deleted_at&&this.user.can(`element:keep`)?(u(),g(x,{key:4},{default:t(()=>[h(B,{"prepend-icon":T.mdiDeleteRestore,variant:`text`,onClick:e=>D.keep(o)},{default:t(()=>[v(y(r.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),this.user.can(`element:purge`)?(u(),g(x,{key:5},{default:t(()=>[h(B,{"prepend-icon":T.mdiDeleteForever,variant:`text`,onClick:e=>D.purge(o)},{default:t(()=>[v(y(r.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0)]),_:2},1032,[`onClick`])]),_:2},1024)]),_:2},1032,[`aria-label`,`modelValue`,`onUpdate:modelValue`]))])]),a(`a`,{href:`#`,class:_([`item-content`,{trashed:o.deleted_at}]),onClick:n(e=>r.$emit(`select`,o),[`prevent`]),title:D.title(o)},[a(`div`,Ee,[a(`div`,De,[o.lang?(u(),d(`span`,$,y(o.lang),1)):i(``,!0),o.publish_at?(u(),g(C,{key:1,class:`publish-at`,icon:T.mdiClockOutline},null,8,[`icon`])):i(``,!0),a(`span`,Oe,y(o.name||r.$gettext(`New`)),1)]),a(`div`,ke,y(o.type),1)]),a(`div`,Ae,[a(`div`,je,y(o.editor),1),a(`div`,Me,y(new Date(o.updated_at).toLocaleString()),1)])],10,Te)]),_:2},1024))),128))]),_:1}),E.loading?(u(),d(`p`,Ne,[v(y(r.$gettext(`Loading`))+` `,1),b[20]||=a(`svg`,{class:`spinner`,width:`32`,height:`32`,fill:`currentColor`,viewBox:`0 0 24 24`,xmlns:`http://www.w3.org/2000/svg`},[a(`circle`,{class:`spin1`,cx:`4`,cy:`12`,r:`3`}),a(`circle`,{class:`spin1 spin2`,cx:`12`,cy:`12`,r:`3`}),a(`circle`,{class:`spin1 spin3`,cx:`20`,cy:`12`,r:`3`})],-1)])):i(``,!0),!E.loading&&!E.items.length?(u(),d(`p`,Pe,y(r.$gettext(`No entries found`)),1)):i(``,!0),E.last>1?(u(),g(se,{key:2,modelValue:E.page,"onUpdate:modelValue":b[14]||=e=>E.page=e,length:E.last},null,8,[`modelValue`,`length`])):i(``,!0),!this.embed&&this.user.can(`element:add`)?(u(),d(`div`,Fe,[h(B,{onClick:b[15]||=e=>E.vschemas=!0,title:r.$gettext(`Add element`),disabled:E.loading,icon:T.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):i(``,!0),(u(),g(s,{to:`body`},[h(R,{modelValue:E.vschemas,"onUpdate:modelValue":b[17]||=e=>E.vschemas=e,onAfterLeave:b[18]||=e=>E.vschemas=!1,scrollable:``,width:`auto`},{default:t(()=>[h(H,null,{default:t(()=>[h(W,null,{default:t(()=>[h(k,{type:`content`,onAdd:b[16]||=e=>D.add(e)})]),_:1})]),_:1})]),_:1},8,[`modelValue`])])),h(A,{modelValue:E.editDialog,"onUpdate:modelValue":b[19]||=e=>E.editDialog=e,count:E.editIds.length,onApply:D.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var Le=L(_e,[[`render`,Ie],[`__scopeId`,`data-v-2703f488`]]);export{Le as t};