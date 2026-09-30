import{Cn as e,D as t,Hn as n,J as r,K as i,P as a,T as o,Tn as s,Ut as c,Y as l,Yt as ee,Zt as u,kt as d,nt as f,q as p,sr as m,tt as h,ur as g}from"./charts-jDwLkx1m.js";import{s as _}from"./graphql-BP2ngTk6.js";import{t as v}from"./VIcon-C3jBxLcD.js";import{$r as y,Et as b,Ft as x,It as S,Rt as C,Sn as w,Tn as T,X as E,bn as D,br as O,c as k,cr as A,ei as j,f as te,fn as ne,fr as M,gr as N,kr as P,n as F,nr as I,nt as L,o as R,or as z,rr as B,vr as V,wn as H,wt as U,yr as W,zt as G}from"./index-nCgNV5DG.js";import{t as K}from"./ActionMenu-ByvlrX5T.js";import{n as q,r as J,t as Y}from"./lists-h2n1AS8a.js";import{t as X}from"./LoadingSpinner-DWxvWHmd.js";import{t as Z}from"./ListSort-DMvlosJ1.js";import{t as re}from"./SchemaDialog-BQ31N5YW.js";import{t as ie}from"./EditBulkDialog-BgT2u20e.js";import{t as Q}from"./VCheckboxBtn-BaB5GeQE.js";import{n as ae,o as oe}from"./files-LFLC7INs.js";import{t as se}from"./VPagination-vpYjYyw8.js";var ce=_`
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
`,le=_`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,ue=_`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,de=_`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,fe=_`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,pe=_`
  mutation ($id: [ID!]!, $input: ElementInput!) {
    bulkElement(id: $id, input: $input) {
      ids
    }
  }
`,me=_`
  ${ae}
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
`,he=Object.freeze([{column:`ID`,order:`DESC`,label:`Latest`},{column:`ID`,order:`ASC`,label:`Oldest`},{column:`LATEST_ID`,order:`DESC`,label:`Latest edit`},{column:`LATEST_ID`,order:`ASC`,label:`Oldest edit`},{column:`NAME`,order:`ASC`,label:`Name`},{column:`TYPE`,order:`ASC`,label:`Type`},{column:`EDITOR`,order:`ASC`,label:`Editor`}]),ge={components:{ActionMenu:K,ListSkeleton:J,LoadingSpinner:X,SchemaDialog:re,EditBulkDialog:ie,ListSort:Z},props:{embed:{type:Boolean,default:!1},defaults:{type:Object,default:null},filter:{type:Object,default:()=>({})}},emits:[`select`],data(){return{items:[],checked:new Set,term:``,sort:this.user.setting(`element`,`sort`,{column:`ID`,order:`DESC`}),page:1,last:1,limit:100,vschemas:!1,editDialog:!1,editIds:[],editSelected:!1,loading:!0,trash:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1}},setup(){q(`element`,e=>e.vschemas=!0);let e=Y(),t=M();return{listKey:e,user:N(),changes:z(),confirm:A(),messages:t,mdiDotsVertical:G,mdiPublish:H,mdiDelete:x,mdiDeleteRestore:C,mdiDeleteForever:S,mdiPlus:w,mdiMagnify:ne,mdiClockOutline:U,mdiRefresh:T,mdiPencil:D,mdiCloseCircleOutline:b,sortOptions:he,debounce:P}},created(){this.search(),this.searchd=this.debounce(this.search,500),this.embed||O(this,`element`,(e,t)=>W(this,e,t))},beforeUnmount(){this.destroyed=!0,V(this),this.items=null,this.checked=null},activated(){this.sync(),this.revalidate()},computed:{filtered(){return this.term||!this.defaults?!0:Object.keys({...this.filter,...this.defaults}).some(e=>e!==`view`&&JSON.stringify(this.filter[e]??null)!==JSON.stringify(this.defaults[e]??null))},canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)}},methods:{resetFilter(){if(this.term=``,this.defaults){let e={};for(let t in this.filter)t!==`view`&&(e[t]=this.defaults[t]??null);Object.assign(this.filter,e)}},add(e){if(this.embed||!this.user.can(`element:add`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}return this.$apollo.mutate({mutation:ce,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.addElement||{};return t.data=y(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(e=>{this.$log(`ElementListItems::add(): Error adding shared element`,e)})},drop(e){if(!this.user.can(`element:drop`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:le,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search(),this.messages.add(this.$ngettext(`Moved to trash`,`%{num} entries moved to trash`,t.length,{num:t.length}),`success`,null,this.user.can(`element:keep`)?{label:this.$gettext(`Undo`),handler:()=>this.keep(t)}:null)}).catch(e=>{this.messages.add(this.$gettext(`Error trashing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::drop(): Error trashing shared element`,t,e)})},reload(){return this.outdated=!1,this.items=[],this.loading=!0,this.$apollo.provider.defaultClient.clearStore().then(()=>this.search())},revalidate(){if(this.loading)return;let e=this.options(),t=this.$apollo.provider.defaultClient.cache;if(e.fetchPolicy===`network-only`||!t.diff({query:e.query,variables:e.variables,returnPartialData:!0}).complete)return this.search()},patch(e){let t=this.items?.find(t=>t.id===e.id);if(!t)return!1;for(let n in e)n in t&&(t[n]=e[n]);return!0},patchItems(e){let t=new Map(e.map(e=>[e.id,e]));this.items?.forEach(e=>{let n=t.get(e.id);if(n)for(let t in n)t in e&&(e[t]=n[t])})},sync(){let e=this.changes.get(`element`).filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched(`element`,e)},invalidate(){I(this.$apollo.provider.defaultClient.cache,`elements`)},options(){let e=this.filter.publish||null,t=this.filter.trashed||`WITHOUT`,n={...this.filter};delete n.publish,delete n.trashed;for(let e in n)n[e]===null&&delete n[e];return this.term&&(n.any=this.term),{query:me,fetchPolicy:B(),variables:{filter:n,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}},keep(e){if(!this.user.can(`element:keep`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=Array.isArray(e)?e:e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ue,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error restoring shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::keep(): Error restoring shared element`,t,e)})},publish(e){if(!this.user.can(`element:publish`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id)&&e.id&&!e.published);t.length&&this.$apollo.mutate({mutation:de,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error publishing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::publish(): Error publishing shared element`,t,e)})},async purge(e){if(!this.user.can(`element:purge`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));!t.length||!await this.confirm.purge(t.map(e=>({name:e.name,info:e.type})))||this.$apollo.mutate({mutation:fe,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error purging shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::purge(): Error purging shared element`,t,e)})},edit(e=null){this.editIds=e?[e.id]:[...this.checked],this.editSelected=!e,this.editDialog=this.editIds.length>0},save(e){if(!this.user.can(`element:save`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=this.editIds,n=this.editSelected?null:new Set(this.checked);if(!(!t.length||e===null))return this.$apollo.mutate({mutation:pe,variables:{id:t,input:{lang:e}}}).then(e=>{if(e.errors)throw e.errors;return this.editIds=[],this.editSelected&&(this.checked=new Set),this.editSelected=!1,this.invalidate(),this.search().then(()=>{n&&(this.checked=n)})}).catch(n=>{this.messages.add(this.$gettext(`Error saving shared element`)+`:
`+n,`error`),this.$log(`ElementListItems::save(): Error saving shared elements`,t,e,n)})},search(){return this.user.can(`element:view`)?(this.loading=!0,this.$apollo.query(this.options()).then(e=>{if(e.errors)throw e.errors;let t=e.data.elements||{};return this.last=t.paginatorInfo?.lastPage||1,this.items=[...t.data||[]].map(e=>{let t=e.latest,r=t?.data?j(t.data):{...e,data:j(e.data)};return r.data&&typeof r.data==`object`&&(r.data=n(r.data)),Object.assign(r,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null,files:Object.freeze((t?.files||e.files||[]).map(oe))})}),this.checked=new Set,this.outdated=!1,this.loading=!1,this.items}).catch(e=>{this.messages.add(this.$gettext(`Error fetching shared elements`)+`:
`+e,`error`),this.$log(`ElementListItems::search(): Error fetching shared element`,e)})):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))},title(e){let t=[];return e.publish_at&&t.push(`Publish at: `+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked=this.checked.size>0?new Set:new Set(this.items.map(e=>e.id))},toggleCheck(e){let t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort(){this.search()}}},_e={class:`header`},ve={class:`bulk`},ye={class:`btn-actions`},be={class:`search`},xe={class:`layout`},Se={class:`actions`},Ce={class:`btn-actions`},we=[`onClick`,`title`],Te={class:`item-text`},Ee={class:`item-head`},De={key:0,class:`item-lang`},$={class:`item-title`},Oe={class:`item-type item-subtitle`},ke={class:`item-aux`},Ae={class:`item-editor`},je={class:`item-modified item-subtitle`},Me={key:1,class:`loading`},Ne={key:2,class:`notfound`},Pe={key:4,class:`btn-group`};function Fe(n,_,y,b,x,S){let C=u(`ActionMenu`),w=u(`ListSort`),T=u(`ListSkeleton`),D=u(`LoadingSpinner`),O=u(`SchemaDialog`),A=u(`EditBulkDialog`);return c(),l(a,null,[i(`div`,_e,[i(`div`,ve,[f(Q,{"model-value":x.checked.size>0,onClick:_[0]||=t(e=>S.toggle(),[`stop`]),"aria-label":n.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),i(`span`,ye,[f(C,null,{activator:e(({props:e,label:t})=>[f(E,d(e,{disabled:!S.isChecked||y.embed||!b.user.can(`element:add`),title:t,icon:b.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:e(()=>[s(f(k,null,{default:e(()=>[f(E,{"prepend-icon":b.mdiPublish,variant:`text`,onClick:_[1]||=e=>S.publish()},{default:e(()=>[h(g(n.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,S.isChecked&&b.user.can(`element:publish`)]]),s(f(k,null,{default:e(()=>[f(E,{"prepend-icon":b.mdiPencil,variant:`text`,onClick:_[2]||=e=>S.edit()},{default:e(()=>[h(g(n.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,S.isChecked&&b.user.can(`element:save`)]]),s(f(k,null,{default:e(()=>[f(E,{"prepend-icon":b.mdiDelete,variant:`text`,onClick:_[3]||=e=>S.drop()},{default:e(()=>[h(g(n.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,S.canTrash&&b.user.can(`element:drop`)]]),s(f(k,null,{default:e(()=>[f(E,{"prepend-icon":b.mdiDeleteRestore,variant:`text`,onClick:_[4]||=e=>S.keep()},{default:e(()=>[h(g(n.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,S.isTrashed&&b.user.can(`element:keep`)]]),s(f(k,null,{default:e(()=>[f(E,{"prepend-icon":b.mdiDeleteForever,variant:`text`,onClick:_[5]||=e=>S.purge()},{default:e(()=>[h(g(n.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[o,S.isChecked&&b.user.can(`element:purge`)]])]),_:1})]),!this.embed&&this.user.can(`element:add`)?(c(),p(E,{key:0,onClick:_[6]||=e=>x.vschemas=!0,title:n.$gettext(`Add element`),disabled:x.loading,icon:b.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):r(``,!0)]),i(`div`,be,[f(te,{ref:`search`,modelValue:x.term,"onUpdate:modelValue":_[7]||=e=>x.term=e,"prepend-inner-icon":b.mdiMagnify,variant:`underlined`,label:n.$gettext(`Search for`),"hide-details":``,clearable:``},null,8,[`modelValue`,`prepend-inner-icon`,`label`])]),i(`div`,xe,[x.outdated?(c(),p(E,{key:0,onClick:_[8]||=e=>S.reload(),"prepend-icon":b.mdiRefresh,title:n.$gettext(`Updated by another user`),color:`warning`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:e(()=>[h(g(n.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):r(``,!0),f(E,{onClick:_[9]||=e=>S.reload(),loading:x.loading,title:n.$gettext(`Reload elements`),icon:b.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`loading`,`title`,`icon`]),f(w,{modelValue:x.sort,"onUpdate:modelValue":_[10]||=e=>x.sort=e,options:b.sortOptions},null,8,[`modelValue`,`options`])])]),f(F,{class:`items`,onKeydown:b.listKey},{default:e(()=>[(c(!0),l(a,null,ee(x.items,a=>(c(),p(k,{key:a.id,"data-id":a.id},{default:e(()=>[i(`div`,Se,[f(Q,{"model-value":x.checked.has(a.id),"onUpdate:modelValue":e=>S.toggleCheck(a),class:m([{draft:!a.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),i(`span`,Ce,[f(C,null,{activator:e(({props:e,label:t})=>[f(E,d({ref_for:!0},e,{title:t,icon:b.mdiDotsVertical,variant:`text`}),null,16,[`title`,`icon`])]),default:e(()=>[s(f(k,null,{default:e(()=>[f(E,{"prepend-icon":b.mdiPublish,variant:`text`,onClick:e=>S.publish(a)},{default:e(()=>[h(g(n.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1536),[[o,!a.deleted_at&&!a.published&&this.user.can(`element:publish`)]]),!a.deleted_at&&!a.published&&b.user.can(`element:publish`)&&b.user.can(`element:save`)?(c(),p(R,{key:0})):r(``,!0),b.user.can(`element:save`)?(c(),p(k,{key:1},{default:e(()=>[f(E,{"prepend-icon":b.mdiPencil,variant:`text`,onClick:e=>S.edit(a)},{default:e(()=>[h(g(n.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0),b.user.can(`element:save`)?(c(),p(R,{key:2})):r(``,!0),!a.deleted_at&&this.user.can(`element:drop`)?(c(),p(k,{key:3},{default:e(()=>[f(E,{"prepend-icon":b.mdiDelete,variant:`text`,onClick:e=>S.drop(a)},{default:e(()=>[h(g(n.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0),a.deleted_at&&this.user.can(`element:keep`)?(c(),p(k,{key:4},{default:e(()=>[f(E,{"prepend-icon":b.mdiDeleteRestore,variant:`text`,onClick:e=>S.keep(a)},{default:e(()=>[h(g(n.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0),this.user.can(`element:purge`)?(c(),p(k,{key:5},{default:e(()=>[f(E,{"prepend-icon":b.mdiDeleteForever,variant:`text`,onClick:e=>S.purge(a)},{default:e(()=>[h(g(n.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):r(``,!0)]),_:2},1024)])]),i(`a`,{href:`#`,class:m([`item-content`,{trashed:a.deleted_at}]),onClick:t(e=>n.$emit(`select`,a),[`prevent`]),title:S.title(a)},[i(`div`,Te,[i(`div`,Ee,[a.lang?(c(),l(`span`,De,g(a.lang),1)):r(``,!0),a.publish_at?(c(),p(v,{key:1,class:`publish-at`,icon:b.mdiClockOutline},null,8,[`icon`])):r(``,!0),i(`span`,$,g(a.name||n.$gettext(`New`)),1)]),i(`div`,Oe,g(a.type),1)]),i(`div`,ke,[i(`div`,Ae,g(a.editor),1),i(`div`,je,g(new Date(a.updated_at).toLocaleString()),1)])],10,we)]),_:2},1032,[`data-id`]))),128))]),_:1},8,[`onKeydown`]),x.loading&&!x.items?.length?(c(),p(T,{key:0})):x.loading?(c(),l(`p`,Me,[h(g(n.$gettext(`Loading`))+` `,1),f(D,{width:`32`,height:`32`})])):r(``,!0),!x.loading&&!x.items.length?(c(),l(`p`,Ne,[S.filtered?(c(),l(a,{key:0},[h(g(n.$gettext(`No entries found`))+` `,1),x.term||y.defaults?(c(),p(E,{key:0,class:`btn-reset-filter`,variant:`text`,"prepend-icon":b.mdiCloseCircleOutline,onClick:_[11]||=e=>S.resetFilter()},{default:e(()=>[h(g(n.$gettext(`Reset`)),1)]),_:1},8,[`prepend-icon`])):r(``,!0)],64)):(c(),l(a,{key:1},[h(g(n.$gettext(`No entries yet`)),1)],64))])):r(``,!0),x.last>1?(c(),p(se,{key:3,modelValue:x.page,"onUpdate:modelValue":_[12]||=e=>x.page=e,length:x.last},null,8,[`modelValue`,`length`])):r(``,!0),!this.embed&&this.user.can(`element:add`)?(c(),l(`div`,Pe,[f(E,{onClick:_[13]||=e=>x.vschemas=!0,title:n.$gettext(`Add element`),disabled:x.loading,icon:b.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):r(``,!0),f(O,{modelValue:x.vschemas,"onUpdate:modelValue":_[14]||=e=>x.vschemas=e,elements:!1,onAdd:_[15]||=e=>S.add(e)},null,8,[`modelValue`]),f(A,{modelValue:x.editDialog,"onUpdate:modelValue":_[16]||=e=>x.editDialog=e,count:x.editIds.length,onApply:S.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var Ie=L(ge,[[`render`,Fe],[`__scopeId`,`data-v-fb5ae924`]]);export{Ie as default};