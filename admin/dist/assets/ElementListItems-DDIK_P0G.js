import{Cn as e,D as t,Hn as n,J as r,K as i,P as a,T as o,Tn as s,Ut as c,Y as l,Yt as u,Zt as d,kt as f,nt as p,q as m,sr as h,tt as g,ur as _}from"./charts-jDwLkx1m.js";import{s as v}from"./graphql-BP2ngTk6.js";import{o as y,t as b}from"./VList-Bmw4Qqso.js";import{t as x}from"./VIcon-C3jBxLcD.js";import{$ as S,Bt as C,Dn as w,Fn as T,H as E,It as D,Nn as O,Ot as k,Pn as A,Q as j,Rt as ee,Sn as te,Un as M,Vt as N,dr as P,jn as F,nt as I,p as L,tt as R,ur as z,vn as B,y as V,yn as H}from"./index-DoQrt7zT.js";import{t as U}from"./ActionMenu-DSa_6t6J.js";import{t as W}from"./VDivider-BIS4pXp5.js";import{t as G}from"./LoadingSpinner-DHx_k_LB.js";import{t as K}from"./SchemaDialog-CX6227rD.js";import{t as q}from"./EditBulkDialog-B-bqOHu4.js";import{t as J}from"./VCheckboxBtn-DHyaZM4N.js";import{t as Y}from"./VTextField-ur4fBEAk.js";import{t as X}from"./ListSort-D8SdnZGr.js";import{n as Z,o as Q}from"./files-CuVD2j-q.js";import{t as ne}from"./VPagination-DgsaxafF.js";var re=v`
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
`,ie=v`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,ae=v`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,oe=v`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,se=v`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,ce=v`
  mutation ($id: [ID!]!, $input: ElementInput!) {
    bulkElement(id: $id, input: $input) {
      ids
    }
  }
`,le=v`
  ${Z}
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
`,ue=Object.freeze([{column:`ID`,order:`DESC`,label:`Latest`},{column:`ID`,order:`ASC`,label:`Oldest`},{column:`LATEST_ID`,order:`DESC`,label:`Latest edit`},{column:`LATEST_ID`,order:`ASC`,label:`Oldest edit`},{column:`NAME`,order:`ASC`,label:`Name`},{column:`TYPE`,order:`ASC`,label:`Type`},{column:`EDITOR`,order:`ASC`,label:`Editor`}]),de={components:{ActionMenu:U,LoadingSpinner:G,SchemaDialog:K,EditBulkDialog:q,ListSort:X},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:[`select`],data(){return{items:[],checked:new Set,term:``,sort:this.user.getData(`element`,`sort`)||{column:`ID`,order:`DESC`},page:1,last:1,limit:100,vschemas:!1,editDialog:!1,editIds:[],editSelected:!1,loading:!0,trash:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1}},setup(){let e=w();return{user:F(),changes:te(),messages:e,mdiDotsVertical:I,mdiPublish:C,mdiDelete:j,mdiDeleteRestore:R,mdiDeleteForever:S,mdiPlus:ee,mdiMagnify:k,mdiClockOutline:E,mdiRefresh:N,mdiPencil:D,sortOptions:ue,debounce:M}},created(){this.search(),this.searchd=this.debounce(this.search,500),this.embed||T(this,`element`,(e,t)=>A(this,e,t))},beforeUnmount(){this.destroyed=!0,O(this),this.items=null,this.checked=null},activated(){this.sync(),this.revalidate()},computed:{canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)}},methods:{add(e){if(this.embed||!this.user.can(`element:add`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}return this.$apollo.mutate({mutation:re,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.addElement||{};return t.data=z(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(e=>{this.$log(`ElementListItems::add(): Error adding shared element`,e)})},drop(e){if(!this.user.can(`element:drop`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ie,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error trashing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::drop(): Error trashing shared element`,t,e)})},reload(){return this.outdated=!1,this.items=[],this.loading=!0,this.$apollo.provider.defaultClient.clearStore().then(()=>this.search())},revalidate(){if(this.loading)return;let e=this.options(),t=this.$apollo.provider.defaultClient.cache;if(e.fetchPolicy===`network-only`||!t.diff({query:e.query,variables:e.variables,returnPartialData:!0}).complete)return this.search()},patch(e){let t=this.items?.find(t=>t.id===e.id);if(!t)return!1;for(let n in e)n in t&&(t[n]=e[n]);return!0},patchItems(e){let t=new Map(e.map(e=>[e.id,e]));this.items?.forEach(e=>{let n=t.get(e.id);if(n)for(let t in n)t in e&&(e[t]=n[t])})},sync(){let e=this.changes.get(`element`).filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched(`element`,e)},invalidate(){B(this.$apollo.provider.defaultClient.cache,`elements`)},options(){let e=this.filter.publish||null,t=this.filter.trashed||`WITHOUT`,n={...this.filter};delete n.publish,delete n.trashed;for(let e in n)n[e]===null&&delete n[e];return this.term&&(n.any=this.term),{query:le,fetchPolicy:H(),variables:{filter:n,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}},keep(e){if(!this.user.can(`element:keep`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ae,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error restoring shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::keep(): Error restoring shared element`,t,e)})},publish(e){if(!this.user.can(`element:publish`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id)&&e.id&&!e.published);t.length&&this.$apollo.mutate({mutation:oe,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error publishing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::publish(): Error publishing shared element`,t,e)})},purge(e){if(!this.user.can(`element:purge`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:se,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error purging shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::purge(): Error purging shared element`,t,e)})},edit(e=null){this.editIds=e?[e.id]:[...this.checked],this.editSelected=!e,this.editDialog=this.editIds.length>0},save(e){if(!this.user.can(`element:save`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=this.editIds,n=this.editSelected?null:new Set(this.checked);if(!(!t.length||e===null))return this.$apollo.mutate({mutation:ce,variables:{id:t,input:{lang:e}}}).then(e=>{if(e.errors)throw e.errors;return this.editIds=[],this.editSelected&&(this.checked=new Set),this.editSelected=!1,this.invalidate(),this.search().then(()=>{n&&(this.checked=n)})}).catch(n=>{this.messages.add(this.$gettext(`Error saving shared element`)+`:
`+n,`error`),this.$log(`ElementListItems::save(): Error saving shared elements`,t,e,n)})},search(){return this.user.can(`element:view`)?(this.loading=!0,this.$apollo.query(this.options()).then(e=>{if(e.errors)throw e.errors;let t=e.data.elements||{};return this.last=t.paginatorInfo?.lastPage||1,this.items=[...t.data||[]].map(e=>{let t=e.latest,r=t?.data?P(t.data):{...e,data:P(e.data)};return r.data&&typeof r.data==`object`&&(r.data=n(r.data)),Object.assign(r,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null,files:Object.freeze((t?.files||e.files||[]).map(Q))})}),this.checked=new Set,this.outdated=!1,this.loading=!1,this.items}).catch(e=>{this.messages.add(this.$gettext(`Error fetching shared elements`)+`:
`+e,`error`),this.$log(`ElementListItems::search(): Error fetching shared element`,e)})):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))},title(e){let t=[];return e.publish_at&&t.push(`Publish at: `+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked=this.checked.size>0?new Set:new Set(this.items.map(e=>e.id))},toggleCheck(e){let t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData(`element`,`sort`,this.sort),this.search()}}}},fe={class:`header`},pe={class:`bulk`},me={class:`btn-actions`},he={class:`search`},ge={class:`layout`},_e={class:`actions`},ve={class:`btn-actions`},ye=[`onClick`,`title`],be={class:`item-text`},$={class:`item-head`},xe={key:0,class:`item-lang`},Se={class:`item-title`},Ce={class:`item-type item-subtitle`},we={class:`item-aux`},Te={class:`item-editor`},Ee={class:`item-modified item-subtitle`},De={key:0,class:`loading`},Oe={key:1,class:`notfound`},ke={key:3,class:`btn-group`};function Ae(n,v,S,C,w,T){let E=d(`ActionMenu`),D=d(`ListSort`),O=d(`LoadingSpinner`),k=d(`SchemaDialog`),A=d(`EditBulkDialog`);return c(),l(a,null,[i(`div`,fe,[i(`div`,pe,[p(J,{"model-value":w.checked.size>0,onClick:v[0]||=t(e=>T.toggle(),[`stop`]),"aria-label":n.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),i(`span`,me,[p(E,null,{activator:e(({props:e,label:t})=>[p(L,f(e,{disabled:!T.isChecked||S.embed||!C.user.can(`element:add`),title:t,icon:C.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:e(()=>[s(p(y,null,{default:e(()=>[p(L,{"prepend-icon":C.mdiPublish,variant:`text`,onClick:v[1]||=e=>T.publish()},{default:e(()=>[g(_(n.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,T.isChecked&&C.user.can(`element:publish`)]]),s(p(y,null,{default:e(()=>[p(L,{"prepend-icon":C.mdiPencil,variant:`text`,onClick:v[2]||=e=>T.edit()},{default:e(()=>[g(_(n.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,T.isChecked&&C.user.can(`element:save`)]]),s(p(y,null,{default:e(()=>[p(L,{"prepend-icon":C.mdiDelete,variant:`text`,onClick:v[3]||=e=>T.drop()},{default:e(()=>[g(_(n.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,T.canTrash&&C.user.can(`element:drop`)]]),s(p(y,null,{default:e(()=>[p(L,{"prepend-icon":C.mdiDeleteRestore,variant:`text`,onClick:v[4]||=e=>T.keep()},{default:e(()=>[g(_(n.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,T.isTrashed&&C.user.can(`element:keep`)]]),s(p(y,null,{default:e(()=>[p(L,{"prepend-icon":C.mdiDeleteForever,variant:`text`,onClick:v[5]||=e=>T.purge()},{default:e(()=>[g(_(n.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,T.isChecked&&C.user.can(`element:purge`)]])]),_:1})]),!this.embed&&this.user.can(`element:add`)?(c(),m(L,{key:0,onClick:v[6]||=e=>w.vschemas=!0,title:n.$gettext(`Add element`),disabled:w.loading,icon:C.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):r(``,!0)]),i(`div`,he,[p(Y,{modelValue:w.term,"onUpdate:modelValue":v[7]||=e=>w.term=e,"prepend-inner-icon":C.mdiMagnify,variant:`underlined`,label:n.$gettext(`Search for`),"hide-details":``,clearable:``},null,8,[`modelValue`,`prepend-inner-icon`,`label`])]),i(`div`,ge,[w.outdated?(c(),m(L,{key:0,onClick:v[8]||=e=>T.reload(),"prepend-icon":C.mdiRefresh,title:n.$gettext(`Updated by another user`),color:`primary`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:e(()=>[g(_(n.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):r(``,!0),p(L,{onClick:v[9]||=e=>T.reload(),title:n.$gettext(`Reload elements`),icon:C.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`title`,`icon`]),p(D,{modelValue:w.sort,"onUpdate:modelValue":v[10]||=e=>w.sort=e,options:C.sortOptions},null,8,[`modelValue`,`options`])])]),p(b,{class:`items`},{default:e(()=>[(c(!0),l(a,null,u(w.items,a=>(c(),m(y,{key:a.id},{default:e(()=>[i(`div`,_e,[p(J,{"model-value":w.checked.has(a.id),"onUpdate:modelValue":e=>T.toggleCheck(a),class:h([{draft:!a.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),i(`span`,ve,[p(E,null,{activator:e(({props:e,label:t})=>[p(L,f({ref_for:!0},e,{title:t,icon:C.mdiDotsVertical,variant:`text`}),null,16,[`title`,`icon`])]),default:e(()=>[s(p(y,null,{default:e(()=>[p(L,{"prepend-icon":C.mdiPublish,variant:`text`,onClick:e=>T.publish(a)},{default:e(()=>[g(_(n.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1536),[[o,!a.deleted_at&&!a.published&&this.user.can(`element:publish`)]]),!a.deleted_at&&!a.published&&C.user.can(`element:publish`)&&C.user.can(`element:save`)?(c(),m(W,{key:0})):r(``,!0),C.user.can(`element:save`)?(c(),m(y,{key:1},{default:e(()=>[p(L,{"prepend-icon":C.mdiPencil,variant:`text`,onClick:e=>T.edit(a)},{default:e(()=>[g(_(n.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0),C.user.can(`element:save`)?(c(),m(W,{key:2})):r(``,!0),!a.deleted_at&&this.user.can(`element:drop`)?(c(),m(y,{key:3},{default:e(()=>[p(L,{"prepend-icon":C.mdiDelete,variant:`text`,onClick:e=>T.drop(a)},{default:e(()=>[g(_(n.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0),a.deleted_at&&this.user.can(`element:keep`)?(c(),m(y,{key:4},{default:e(()=>[p(L,{"prepend-icon":C.mdiDeleteRestore,variant:`text`,onClick:e=>T.keep(a)},{default:e(()=>[g(_(n.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0),this.user.can(`element:purge`)?(c(),m(y,{key:5},{default:e(()=>[p(L,{"prepend-icon":C.mdiDeleteForever,variant:`text`,onClick:e=>T.purge(a)},{default:e(()=>[g(_(n.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0)]),_:2},1024)])]),i(`a`,{href:`#`,class:h([`item-content`,{trashed:a.deleted_at}]),onClick:t(e=>n.$emit(`select`,a),[`prevent`]),title:T.title(a)},[i(`div`,be,[i(`div`,$,[a.lang?(c(),l(`span`,xe,_(a.lang),1)):r(``,!0),a.publish_at?(c(),m(x,{key:1,class:`publish-at`,icon:C.mdiClockOutline},null,8,[`icon`])):r(``,!0),i(`span`,Se,_(a.name||n.$gettext(`New`)),1)]),i(`div`,Ce,_(a.type),1)]),i(`div`,we,[i(`div`,Te,_(a.editor),1),i(`div`,Ee,_(new Date(a.updated_at).toLocaleString()),1)])],10,ye)]),_:2},1024))),128))]),_:1}),w.loading?(c(),l(`p`,De,[g(_(n.$gettext(`Loading`))+` `,1),p(O,{width:`32`,height:`32`})])):r(``,!0),!w.loading&&!w.items.length?(c(),l(`p`,Oe,_(n.$gettext(`No entries found`)),1)):r(``,!0),w.last>1?(c(),m(ne,{key:2,modelValue:w.page,"onUpdate:modelValue":v[11]||=e=>w.page=e,length:w.last},null,8,[`modelValue`,`length`])):r(``,!0),!this.embed&&this.user.can(`element:add`)?(c(),l(`div`,ke,[p(L,{onClick:v[12]||=e=>w.vschemas=!0,title:n.$gettext(`Add element`),disabled:w.loading,icon:C.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):r(``,!0),p(k,{modelValue:w.vschemas,"onUpdate:modelValue":v[13]||=e=>w.vschemas=e,elements:!1,onAdd:v[14]||=e=>T.add(e)},null,8,[`modelValue`]),p(A,{modelValue:w.editDialog,"onUpdate:modelValue":v[15]||=e=>w.editDialog=e,count:w.editIds.length,onApply:T.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var je=V(de,[[`render`,Ae],[`__scopeId`,`data-v-5f07e89e`]]);export{je as default};