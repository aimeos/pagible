import{At as e,E as t,En as n,F as r,J as i,O as a,Qt as o,Un as s,Wt as c,X as l,Xt as u,Y as d,cr as f,dr as p,nt as m,q as h,rt as g,wn as _}from"./charts-BFwsnyip.js";import{s as v}from"./graphql-CNGOqxVc.js";import{_ as y,v as b}from"./i18n-BIsbIWbx.js";import{C as x,I as S,L as C,N as w,P as T,St as E,_t as D,lt as O,xt as k,yt as A}from"./mdi-DjcIfWd7.js";import{t as ee}from"./VIcon-Db85FLnB.js";import{G as te,H as j,K as M,M as N,O as P,P as F,R as I,S as L,T as R,W as z,a as B,et as V,k as H,s as U,t as W}from"./index-CqCt1zG9.js";import{t as G}from"./ActionMenu-D01bw22g.js";import{t as K}from"./LoadingSpinner-DKUjsYqJ.js";import{t as q}from"./SchemaDialog-h4DmwIzB.js";import{t as J}from"./EditBulkDialog-B3q5A6iw.js";import{t as Y}from"./VCheckboxBtn-BAL-Kq6z.js";import{t as X}from"./VTextField-DoJtHKQQ.js";import{t as Z}from"./ListSort-D1iskD5H.js";import{n as Q,o as ne}from"./files-BhKwLOSY.js";import{t as re}from"./VPagination-BtcmaWtH.js";var ie=v`
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
`,ae=v`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,oe=v`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,se=v`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,ce=v`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,le=v`
  mutation ($id: [ID!]!, $input: ElementInput!) {
    bulkElement(id: $id, input: $input) {
      ids
    }
  }
`,ue=v`
  ${Q}
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
`,de=Object.freeze([{column:`ID`,order:`DESC`,label:`Latest`},{column:`ID`,order:`ASC`,label:`Oldest`},{column:`LATEST_ID`,order:`DESC`,label:`Latest edit`},{column:`LATEST_ID`,order:`ASC`,label:`Oldest edit`},{column:`NAME`,order:`ASC`,label:`Name`},{column:`TYPE`,order:`ASC`,label:`Type`},{column:`EDITOR`,order:`ASC`,label:`Editor`}]),fe={components:{ActionMenu:G,LoadingSpinner:K,SchemaDialog:q,EditBulkDialog:J,ListSort:Z},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:[`select`],data(){return{items:[],checked:new Set,term:``,sort:this.user.getData(`element`,`sort`)||{column:`ID`,order:`DESC`},page:1,last:1,limit:100,vschemas:!1,editDialog:!1,editIds:[],editSelected:!1,loading:!0,trash:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1}},setup(){let e=I();return{user:j(),changes:N(),confirm:F(),messages:e,mdiDotsVertical:C,mdiPublish:k,mdiDelete:w,mdiDeleteRestore:S,mdiDeleteForever:T,mdiPlus:A,mdiMagnify:O,mdiClockOutline:x,mdiRefresh:E,mdiPencil:D,sortOptions:de,debounce:V}},created(){this.search(),this.searchd=this.debounce(this.search,500),this.embed||M(this,`element`,(e,t)=>te(this,e,t))},beforeUnmount(){this.destroyed=!0,z(this),this.items=null,this.checked=null},activated(){this.sync(),this.revalidate()},computed:{canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)}},methods:{add(e){if(this.embed||!this.user.can(`element:add`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}return this.$apollo.mutate({mutation:ie,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.addElement||{};return t.data=y(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(e=>{this.$log(`ElementListItems::add(): Error adding shared element`,e)})},drop(e){if(!this.user.can(`element:drop`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ae,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error trashing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::drop(): Error trashing shared element`,t,e)})},reload(){return this.outdated=!1,this.items=[],this.loading=!0,this.$apollo.provider.defaultClient.clearStore().then(()=>this.search())},revalidate(){if(this.loading)return;let e=this.options(),t=this.$apollo.provider.defaultClient.cache;if(e.fetchPolicy===`network-only`||!t.diff({query:e.query,variables:e.variables,returnPartialData:!0}).complete)return this.search()},patch(e){let t=this.items?.find(t=>t.id===e.id);if(!t)return!1;for(let n in e)n in t&&(t[n]=e[n]);return!0},patchItems(e){let t=new Map(e.map(e=>[e.id,e]));this.items?.forEach(e=>{let n=t.get(e.id);if(n)for(let t in n)t in e&&(e[t]=n[t])})},sync(){let e=this.changes.get(`element`).filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched(`element`,e)},invalidate(){P(this.$apollo.provider.defaultClient.cache,`elements`)},options(){let e=this.filter.publish||null,t=this.filter.trashed||`WITHOUT`,n={...this.filter};delete n.publish,delete n.trashed;for(let e in n)n[e]===null&&delete n[e];return this.term&&(n.any=this.term),{query:ue,fetchPolicy:H(),variables:{filter:n,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}},keep(e){if(!this.user.can(`element:keep`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:oe,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error restoring shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::keep(): Error restoring shared element`,t,e)})},publish(e){if(!this.user.can(`element:publish`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id)&&e.id&&!e.published);t.length&&this.$apollo.mutate({mutation:se,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error publishing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::publish(): Error publishing shared element`,t,e)})},async purge(e){if(!this.user.can(`element:purge`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&await this.confirm.purge(t.map(e=>({name:e.name,info:e.type})))&&this.$apollo.mutate({mutation:ce,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error purging shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::purge(): Error purging shared element`,t,e)})},edit(e=null){this.editIds=e?[e.id]:[...this.checked],this.editSelected=!e,this.editDialog=this.editIds.length>0},save(e){if(!this.user.can(`element:save`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=this.editIds,n=this.editSelected?null:new Set(this.checked);if(t.length&&e!==null)return this.$apollo.mutate({mutation:le,variables:{id:t,input:{lang:e}}}).then(e=>{if(e.errors)throw e.errors;return this.editIds=[],this.editSelected&&(this.checked=new Set),this.editSelected=!1,this.invalidate(),this.search().then(()=>{n&&(this.checked=n)})}).catch(n=>{this.messages.add(this.$gettext(`Error saving shared element`)+`:
`+n,`error`),this.$log(`ElementListItems::save(): Error saving shared elements`,t,e,n)})},search(){return this.user.can(`element:view`)?(this.loading=!0,this.$apollo.query(this.options()).then(e=>{if(e.errors)throw e.errors;let t=e.data.elements||{};return this.last=t.paginatorInfo?.lastPage||1,this.items=[...t.data||[]].map(e=>{let t=e.latest,n=t?.data?b(t.data):{...e,data:b(e.data)};return n.data&&typeof n.data==`object`&&(n.data=s(n.data)),Object.assign(n,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null,files:Object.freeze((t?.files||e.files||[]).map(ne))})}),this.checked=new Set,this.outdated=!1,this.loading=!1,this.items}).catch(e=>{this.messages.add(this.$gettext(`Error fetching shared elements`)+`:
`+e,`error`),this.$log(`ElementListItems::search(): Error fetching shared element`,e)})):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))},title(e){let t=[];return e.publish_at&&t.push(`Publish at: `+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked=this.checked.size>0?new Set:new Set(this.items.map(e=>e.id))},toggleCheck(e){let t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData(`element`,`sort`,this.sort),this.search()}}}},pe={class:`header`},me={class:`bulk`},he={class:`btn-actions`},ge={class:`search`},_e={class:`layout`},ve={class:`actions`},ye={class:`btn-actions`},be=[`onClick`,`title`],$={class:`item-text`},xe={class:`item-head`},Se={key:0,class:`item-lang`},Ce={class:`item-title`},we={class:`item-type item-subtitle`},Te={class:`item-aux`},Ee={class:`item-editor`},De={class:`item-modified item-subtitle`},Oe={key:0,class:`loading`},ke={key:1,class:`notfound`},Ae={key:3,class:`btn-group`};function je(s,v,y,b,x,S){let C=o(`ActionMenu`),w=o(`ListSort`),T=o(`LoadingSpinner`),E=o(`SchemaDialog`),D=o(`EditBulkDialog`);return c(),l(r,null,[h(`div`,pe,[h(`div`,me,[g(Y,{"model-value":x.checked.size>0,onClick:v[0]||=a(e=>S.toggle(),[`stop`]),"aria-label":s.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),h(`span`,he,[g(C,null,{activator:_(({props:t,label:n})=>[g(L,e(t,{disabled:!S.isChecked||y.embed||!b.user.can(`element:add`),title:n,icon:b.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:_(()=>[n(g(U,null,{default:_(()=>[g(L,{"prepend-icon":b.mdiPublish,variant:`text`,onClick:v[1]||=e=>S.publish()},{default:_(()=>[m(p(s.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[t,S.isChecked&&b.user.can(`element:publish`)]]),n(g(U,null,{default:_(()=>[g(L,{"prepend-icon":b.mdiPencil,variant:`text`,onClick:v[2]||=e=>S.edit()},{default:_(()=>[m(p(s.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[t,S.isChecked&&b.user.can(`element:save`)]]),n(g(U,null,{default:_(()=>[g(L,{"prepend-icon":b.mdiDelete,variant:`text`,onClick:v[3]||=e=>S.drop()},{default:_(()=>[m(p(s.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[t,S.canTrash&&b.user.can(`element:drop`)]]),n(g(U,null,{default:_(()=>[g(L,{"prepend-icon":b.mdiDeleteRestore,variant:`text`,onClick:v[4]||=e=>S.keep()},{default:_(()=>[m(p(s.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[t,S.isTrashed&&b.user.can(`element:keep`)]]),n(g(U,null,{default:_(()=>[g(L,{"prepend-icon":b.mdiDeleteForever,variant:`text`,onClick:v[5]||=e=>S.purge()},{default:_(()=>[m(p(s.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[t,S.isChecked&&b.user.can(`element:purge`)]])]),_:1})]),!this.embed&&this.user.can(`element:add`)?(c(),i(L,{key:0,onClick:v[6]||=e=>x.vschemas=!0,title:s.$gettext(`Add element`),disabled:x.loading,icon:b.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):d(``,!0)]),h(`div`,ge,[g(X,{modelValue:x.term,"onUpdate:modelValue":v[7]||=e=>x.term=e,"prepend-inner-icon":b.mdiMagnify,variant:`underlined`,label:s.$gettext(`Search for`),"hide-details":``,clearable:``},null,8,[`modelValue`,`prepend-inner-icon`,`label`])]),h(`div`,_e,[x.outdated?(c(),i(L,{key:0,onClick:v[8]||=e=>S.reload(),"prepend-icon":b.mdiRefresh,title:s.$gettext(`Updated by another user`),color:`primary`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:_(()=>[m(p(s.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):d(``,!0),g(L,{onClick:v[9]||=e=>S.reload(),title:s.$gettext(`Reload elements`),icon:b.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`title`,`icon`]),g(w,{modelValue:x.sort,"onUpdate:modelValue":v[10]||=e=>x.sort=e,options:b.sortOptions},null,8,[`modelValue`,`options`])])]),g(W,{class:`items`},{default:_(()=>[(c(!0),l(r,null,u(x.items,r=>(c(),i(U,{key:r.id},{default:_(()=>[h(`div`,ve,[g(Y,{"model-value":x.checked.has(r.id),"onUpdate:modelValue":e=>S.toggleCheck(r),class:f([{draft:!r.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),h(`span`,ye,[g(C,null,{activator:_(({props:t,label:n})=>[g(L,e({ref_for:!0},t,{title:n,icon:b.mdiDotsVertical,variant:`text`}),null,16,[`title`,`icon`])]),default:_(()=>[n(g(U,null,{default:_(()=>[g(L,{"prepend-icon":b.mdiPublish,variant:`text`,onClick:e=>S.publish(r)},{default:_(()=>[m(p(s.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1536),[[t,!r.deleted_at&&!r.published&&this.user.can(`element:publish`)]]),!r.deleted_at&&!r.published&&b.user.can(`element:publish`)&&b.user.can(`element:save`)?(c(),i(B,{key:0})):d(``,!0),b.user.can(`element:save`)?(c(),i(U,{key:1},{default:_(()=>[g(L,{"prepend-icon":b.mdiPencil,variant:`text`,onClick:e=>S.edit(r)},{default:_(()=>[m(p(s.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):d(``,!0),b.user.can(`element:save`)?(c(),i(B,{key:2})):d(``,!0),!r.deleted_at&&this.user.can(`element:drop`)?(c(),i(U,{key:3},{default:_(()=>[g(L,{"prepend-icon":b.mdiDelete,variant:`text`,onClick:e=>S.drop(r)},{default:_(()=>[m(p(s.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):d(``,!0),r.deleted_at&&this.user.can(`element:keep`)?(c(),i(U,{key:4},{default:_(()=>[g(L,{"prepend-icon":b.mdiDeleteRestore,variant:`text`,onClick:e=>S.keep(r)},{default:_(()=>[m(p(s.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):d(``,!0),this.user.can(`element:purge`)?(c(),i(U,{key:5},{default:_(()=>[g(L,{"prepend-icon":b.mdiDeleteForever,variant:`text`,onClick:e=>S.purge(r)},{default:_(()=>[m(p(s.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):d(``,!0)]),_:2},1024)])]),h(`a`,{href:`#`,class:f([`item-content`,{trashed:r.deleted_at}]),onClick:a(e=>s.$emit(`select`,r),[`prevent`]),title:S.title(r)},[h(`div`,$,[h(`div`,xe,[r.lang?(c(),l(`span`,Se,p(r.lang),1)):d(``,!0),r.publish_at?(c(),i(ee,{key:1,class:`publish-at`,icon:b.mdiClockOutline},null,8,[`icon`])):d(``,!0),h(`span`,Ce,p(r.name||s.$gettext(`New`)),1)]),h(`div`,we,p(r.type),1)]),h(`div`,Te,[h(`div`,Ee,p(r.editor),1),h(`div`,De,p(new Date(r.updated_at).toLocaleString()),1)])],10,be)]),_:2},1024))),128))]),_:1}),x.loading?(c(),l(`p`,Oe,[m(p(s.$gettext(`Loading`))+` `,1),g(T,{width:`32`,height:`32`})])):d(``,!0),!x.loading&&!x.items.length?(c(),l(`p`,ke,p(s.$gettext(`No entries found`)),1)):d(``,!0),x.last>1?(c(),i(re,{key:2,modelValue:x.page,"onUpdate:modelValue":v[11]||=e=>x.page=e,length:x.last},null,8,[`modelValue`,`length`])):d(``,!0),!this.embed&&this.user.can(`element:add`)?(c(),l(`div`,Ae,[g(L,{onClick:v[12]||=e=>x.vschemas=!0,title:s.$gettext(`Add element`),disabled:x.loading,icon:b.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):d(``,!0),g(E,{modelValue:x.vschemas,"onUpdate:modelValue":v[13]||=e=>x.vschemas=e,elements:!1,onAdd:v[14]||=e=>S.add(e)},null,8,[`modelValue`]),g(D,{modelValue:x.editDialog,"onUpdate:modelValue":v[15]||=e=>x.editDialog=e,count:x.editIds.length,onApply:S.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var Me=R(fe,[[`render`,je],[`__scopeId`,`data-v-d74fd29e`]]);export{Me as default};