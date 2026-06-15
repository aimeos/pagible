import{A as e,B as t,Bt as n,H as r,K as i,Lt as a,W as o,c as s,g as c,h as l,lt as u,m as d,nt as f,o as p,p as m,rt as h,v as g,y as _}from"./charts-DTL_L5t2.js";import{in as v,nn as y}from"./dimensions-Et9-tVY9.js";import{r as b}from"./graphql-C29JtVna.js";import{o as x,t as S}from"./VList-BvmGlkAE.js";import{t as C}from"./VIcon-D6DPr-gc.js";import{An as w,Bt as T,Ct as E,Et as D,In as O,J as k,Ln as A,Mt as j,Nt as ee,On as te,Pt as M,Q as N,R as P,Y as F,Z as I,an as L,b as R,gn as z,in as B,m as V,o as H,r as U,s as W,t as G,wn as K,xn as q,z as J}from"./index-f624Xu9_.js";import{r as Y,t as X}from"./echo-CkGvIPgT.js";import{t as Z}from"./VCheckboxBtn-CYSddSQD.js";import{t as Q}from"./VPagination-DVc61XKg.js";import{t as ne}from"./VTextField-C_YsxKcl.js";import{t as $}from"./SchemaItems-BSMK1dGk.js";var re=b`
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
`,ie=b`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,ae=b`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,oe=b`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,se=b`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,ce=b`
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
        latest {
          id
          published
          publish_at
          data
          editor
          created_at
        }
      }
      paginatorInfo {
        lastPage
      }
    }
  }
`,le={components:{SchemaItems:$},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:[`select`],data(){return{items:[],menu:[],checked:new Set,term:``,sort:this.user.getData(`element`,`sort`)||{column:`ID`,order:`DESC`},page:1,last:1,limit:100,vschemas:!1,actions:!1,loading:!0,trash:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1}},setup(){let e=q();return{user:K(),changes:z(),messages:e,mdiDotsVertical:N,mdiClose:J,mdiPublish:ee,mdiDelete:k,mdiDeleteRestore:I,mdiDeleteForever:F,mdiPlus:j,mdiMagnify:E,mdiMenuDown:D,mdiSort:T,mdiClockOutline:P,mdiRefresh:M,debounce:te}},created(){this.search(),this.searchd=this.debounce(this.search,500),this.embed||Y(this,`element`,null,e=>{if(e.editor!==this.user.me?.email){if(e.action!==`saved`){this.outdated=!0;return}this.patch({...A(e.data),id:e.id,published:e.published,deleted_at:e.deleted_at,publish_at:e.publish_at,updated_at:e.updated_at,editor:e.editor,latest_id:e.latest_id})}})},beforeUnmount(){this.destroyed=!0,X(this),this.items=null,this.menu=null,this.checked=null},activated(){this.sync()},computed:{canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)}},methods:{add(e){if(this.embed||!this.user.can(`element:add`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}return this.$apollo.mutate({mutation:re,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.addElement||{};return t.data=w(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(e=>{this.$log(`ElementListItems::add(): Error adding shared element`,e)})},drop(e){if(!this.user.can(`element:drop`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ie,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error trashing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::drop(): Error trashing shared element`,t,e)})},reload(){this.outdated=!1,this.items=[],this.loading=!0,this.invalidate(),this.search()},patch(e){let t=this.items?.find(t=>t.id===e.id);if(!t)return!1;for(let n in e)n in t&&(t[n]=e[n]);return!0},sync(){let e=this.changes.get(`element`).filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched(`element`,e)},invalidate(){let e=this.$apollo.provider.defaultClient.cache;e.evict({id:`ROOT_QUERY`,fieldName:`elements`}),e.gc()},keep(e){if(!this.user.can(`element:keep`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:ae,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error restoring shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::keep(): Error restoring shared element`,t,e)})},publish(e){if(!this.user.can(`element:publish`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id)&&e.id&&!e.published);t.length&&this.$apollo.mutate({mutation:oe,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error publishing shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::publish(): Error publishing shared element`,t,e)})},purge(e){if(!this.user.can(`element:purge`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:se,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(e=>{this.messages.add(this.$gettext(`Error purging shared element`)+`:
`+e,`error`),this.$log(`ElementListItems::purge(): Error purging shared element`,t,e)})},setSort(e,t){this.sort={column:e,order:t}},search(){if(!this.user.can(`element:view`))return this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]);let e=this.filter.publish||null,t=this.filter.trashed||`WITHOUT`,n={...this.filter};delete n.publish,delete n.trashed;for(let e in n)n[e]===null&&delete n[e];return this.term&&(n.any=this.term),this.loading=!0,this.$apollo.query({query:ce,fetchPolicy:`no-cache`,variables:{filter:n,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}).then(e=>{if(e.errors)throw e.errors;let t=e.data.elements||{};return this.last=t.paginatorInfo?.lastPage||1,this.items=[...t.data||[]].map(e=>{let t=e.latest?.data?O(e.latest?.data):{...e,data:O(e.data)};return t.data&&typeof t.data==`object`&&(t.data=u(t.data)),Object.assign(t,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null})}),this.checked=new Set,this.loading=!1,this.items}).catch(e=>{this.messages.add(this.$gettext(`Error fetching shared elements`)+`:
`+e,`error`),this.$log(`ElementListItems::search(): Error fetching shared element`,e)})},title(e){let t=[];return e.publish_at&&t.push(`Publish at: `+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked.size>0?this.checked=new Set:this.checked=new Set(this.items.map(e=>e.id))},toggleCheck(e){let t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData(`element`,`sort`,this.sort),this.search()}}}},ue={class:`header`},de={class:`bulk`},fe={class:`btn-actions`},pe={class:`search`},me={class:`layout`},he={class:`btn-sort`},ge={class:`actions`},_e={class:`btn-actions`},ve=[`onClick`,`title`],ye={class:`item-text`},be={class:`item-head`},xe={key:0,class:`item-lang`},Se={class:`item-title`},Ce={class:`item-type item-subtitle`},we={class:`item-aux`},Te={class:`item-editor`},Ee={class:`item-modified item-subtitle`},De={key:0,class:`loading`},Oe={key:1,class:`notfound`},ke={key:3,class:`btn-group`};function Ae(u,b,w,T,E,D){let O=o(`SchemaItems`);return t(),c(p,null,[m(`div`,ue,[m(`div`,de,[_(Z,{"model-value":E.checked.size>0,onClick:b[0]||=v(e=>D.toggle(),[`stop`]),"aria-label":u.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),m(`span`,fe,[(t(),d(i(u.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":u.$gettext(`Actions`),modelValue:E.actions,"onUpdate:modelValue":b[7]||=e=>E.actions=e,transition:`scale-transition`,location:`end center`,"max-width":`300`},{activator:f(({props:t})=>[_(V,e(t,{disabled:!D.isChecked||w.embed||!T.user.can(`element:add`),title:u.$gettext(`Actions`),icon:T.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:f(()=>[_(H,null,{default:f(()=>[_(G,{density:`compact`},{default:f(()=>[_(U,null,{default:f(()=>[g(n(u.$gettext(`Actions`)),1)]),_:1}),_(V,{icon:T.mdiClose,"aria-label":u.$gettext(`Close`),onClick:b[1]||=e=>E.actions=!1},null,8,[`icon`,`aria-label`])]),_:1}),_(S,{onClick:b[6]||=e=>E.actions=!1},{default:f(()=>[h(_(x,null,{default:f(()=>[_(V,{"prepend-icon":T.mdiPublish,variant:`text`,onClick:b[2]||=e=>D.publish()},{default:f(()=>[g(n(u.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[y,D.isChecked&&T.user.can(`element:publish`)]]),h(_(x,null,{default:f(()=>[_(V,{"prepend-icon":T.mdiDelete,variant:`text`,onClick:b[3]||=e=>D.drop()},{default:f(()=>[g(n(u.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[y,D.canTrash&&T.user.can(`element:drop`)]]),h(_(x,null,{default:f(()=>[_(V,{"prepend-icon":T.mdiDeleteRestore,variant:`text`,onClick:b[4]||=e=>D.keep()},{default:f(()=>[g(n(u.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[y,D.isTrashed&&T.user.can(`element:keep`)]]),h(_(x,null,{default:f(()=>[_(V,{"prepend-icon":T.mdiDeleteForever,variant:`text`,onClick:b[5]||=e=>D.purge()},{default:f(()=>[g(n(u.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`])]),_:1},512),[[y,D.isChecked&&T.user.can(`element:purge`)]])]),_:1})]),_:1})]),_:1},8,[`aria-label`,`modelValue`]))]),!this.embed&&this.user.can(`element:add`)?(t(),d(V,{key:0,onClick:b[8]||=e=>E.vschemas=!0,title:u.$gettext(`Add element`),disabled:E.loading,icon:T.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):l(``,!0)]),m(`div`,pe,[_(ne,{modelValue:E.term,"onUpdate:modelValue":b[9]||=e=>E.term=e,"prepend-inner-icon":T.mdiMagnify,variant:`underlined`,label:u.$gettext(`Search for`),"hide-details":``,clearable:``},null,8,[`modelValue`,`prepend-inner-icon`,`label`])]),m(`div`,me,[E.outdated?(t(),d(V,{key:0,onClick:b[10]||=e=>D.reload(),"prepend-icon":T.mdiRefresh,title:u.$gettext(`Updated by another user`),color:`primary`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:f(()=>[g(n(u.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):l(``,!0),_(V,{onClick:b[11]||=e=>D.reload(),title:u.$gettext(`Reload elements`),icon:T.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`title`,`icon`]),m(`span`,he,[_(B,null,{activator:f(({props:t})=>[_(V,e(t,{title:u.$gettext(`Sort by`),"append-icon":T.mdiMenuDown,"prepend-icon":T.mdiSort,variant:`text`}),{default:f(()=>[g(n(E.sort?.column===`ID`?E.sort?.order===`DESC`?u.$gettext(`latest`):u.$gettext(`oldest`):E.sort?.column||``),1)]),_:1},16,[`title`,`append-icon`,`prepend-icon`])]),default:f(()=>[_(S,null,{default:f(()=>[_(x,null,{default:f(()=>[_(V,{variant:`text`,onClick:b[12]||=e=>D.setSort(`ID`,`DESC`)},{default:f(()=>[g(n(u.$gettext(`latest`)),1)]),_:1})]),_:1}),_(x,null,{default:f(()=>[_(V,{variant:`text`,onClick:b[13]||=e=>D.setSort(`ID`,`ASC`)},{default:f(()=>[g(n(u.$gettext(`oldest`)),1)]),_:1})]),_:1}),_(x,null,{default:f(()=>[_(V,{variant:`text`,onClick:b[14]||=e=>D.setSort(`NAME`,`ASC`)},{default:f(()=>[g(n(u.$gettext(`name`)),1)]),_:1})]),_:1}),_(x,null,{default:f(()=>[_(V,{variant:`text`,onClick:b[15]||=e=>D.setSort(`TYPE`,`ASC`)},{default:f(()=>[g(n(u.$gettext(`type`)),1)]),_:1})]),_:1}),_(x,null,{default:f(()=>[_(V,{variant:`text`,onClick:b[16]||=e=>D.setSort(`EDITOR`,`ASC`)},{default:f(()=>[g(n(u.$gettext(`editor`)),1)]),_:1})]),_:1})]),_:1})]),_:1})])])]),_(S,{class:`items`},{default:f(()=>[(t(!0),c(p,null,r(E.items,(r,o)=>(t(),d(x,{key:o},{default:f(()=>[m(`div`,ge,[_(Z,{"model-value":E.checked.has(r.id),"onUpdate:modelValue":e=>D.toggleCheck(r),class:a([{draft:!r.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),m(`span`,_e,[(t(),d(i(u.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":u.$gettext(`Actions`),modelValue:E.menu[o],"onUpdate:modelValue":e=>E.menu[o]=e,transition:`scale-transition`,location:`end center`,"max-width":`300`},{activator:f(({props:t})=>[_(V,e({ref_for:!0},t,{title:u.$gettext(`Actions`),icon:T.mdiDotsVertical,variant:`text`}),null,16,[`title`,`icon`])]),default:f(()=>[_(H,null,{default:f(()=>[_(G,{density:`compact`},{default:f(()=>[_(U,null,{default:f(()=>[g(n(u.$gettext(`Actions`)),1)]),_:1}),_(V,{icon:T.mdiClose,"aria-label":u.$gettext(`Close`),onClick:e=>E.menu[o]=!1},null,8,[`icon`,`aria-label`,`onClick`])]),_:2},1024),_(S,{onClick:e=>E.menu[o]=!1},{default:f(()=>[h(_(x,null,{default:f(()=>[_(V,{"prepend-icon":T.mdiPublish,variant:`text`,onClick:e=>D.publish(r)},{default:f(()=>[g(n(u.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1536),[[y,!r.deleted_at&&!r.published&&this.user.can(`element:publish`)]]),!r.deleted_at&&this.user.can(`element:drop`)?(t(),d(x,{key:0},{default:f(()=>[_(V,{"prepend-icon":T.mdiDelete,variant:`text`,onClick:e=>D.drop(r)},{default:f(()=>[g(n(u.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):l(``,!0),r.deleted_at&&this.user.can(`element:keep`)?(t(),d(x,{key:1},{default:f(()=>[_(V,{"prepend-icon":T.mdiDeleteRestore,variant:`text`,onClick:e=>D.keep(r)},{default:f(()=>[g(n(u.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):l(``,!0),this.user.can(`element:purge`)?(t(),d(x,{key:2},{default:f(()=>[_(V,{"prepend-icon":T.mdiDeleteForever,variant:`text`,onClick:e=>D.purge(r)},{default:f(()=>[g(n(u.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):l(``,!0)]),_:2},1032,[`onClick`])]),_:2},1024)]),_:2},1032,[`aria-label`,`modelValue`,`onUpdate:modelValue`]))])]),m(`a`,{href:`#`,class:a([`item-content`,{trashed:r.deleted_at}]),onClick:v(e=>u.$emit(`select`,r),[`prevent`]),title:D.title(r)},[m(`div`,ye,[m(`div`,be,[r.lang?(t(),c(`span`,xe,n(r.lang),1)):l(``,!0),r.publish_at?(t(),d(C,{key:1,class:`publish-at`,icon:T.mdiClockOutline},null,8,[`icon`])):l(``,!0),m(`span`,Se,n(r.name||u.$gettext(`New`)),1)]),m(`div`,Ce,n(r.type),1)]),m(`div`,we,[m(`div`,Te,n(r.editor),1),m(`div`,Ee,n(new Date(r.updated_at).toLocaleString()),1)])],10,ve)]),_:2},1024))),128))]),_:1}),E.loading?(t(),c(`p`,De,[g(n(u.$gettext(`Loading`))+` `,1),b[22]||=m(`svg`,{class:`spinner`,width:`32`,height:`32`,fill:`currentColor`,viewBox:`0 0 24 24`,xmlns:`http://www.w3.org/2000/svg`},[m(`circle`,{class:`spin1`,cx:`4`,cy:`12`,r:`3`}),m(`circle`,{class:`spin1 spin2`,cx:`12`,cy:`12`,r:`3`}),m(`circle`,{class:`spin1 spin3`,cx:`20`,cy:`12`,r:`3`})],-1)])):l(``,!0),!E.loading&&!E.items.length?(t(),c(`p`,Oe,n(u.$gettext(`No entries found`)),1)):l(``,!0),E.last>1?(t(),d(Q,{key:2,modelValue:E.page,"onUpdate:modelValue":b[17]||=e=>E.page=e,length:E.last},null,8,[`modelValue`,`length`])):l(``,!0),!this.embed&&this.user.can(`element:add`)?(t(),c(`div`,ke,[_(V,{onClick:b[18]||=e=>E.vschemas=!0,title:u.$gettext(`Add element`),disabled:E.loading,icon:T.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):l(``,!0),(t(),d(s,{to:`body`},[_(L,{modelValue:E.vschemas,"onUpdate:modelValue":b[20]||=e=>E.vschemas=e,onAfterLeave:b[21]||=e=>E.vschemas=!1,scrollable:``,width:`auto`},{default:f(()=>[_(H,null,{default:f(()=>[_(W,null,{default:f(()=>[_(O,{type:`content`,onAdd:b[19]||=e=>D.add(e)})]),_:1})]),_:1})]),_:1},8,[`modelValue`])]))],64)}var je=R(le,[[`render`,Ae],[`__scopeId`,`data-v-afa03c11`]]);export{je as t};