import{$t as e,Cn as t,D as n,Hn as r,J as i,K as a,P as o,T as s,Tn as ee,Ut as c,Y as l,Yt as u,Zt as d,kt as f,nt as p,q as m,sr as h,tt as g,ur as _}from"./charts-D3TmX40N.js";import{s as v}from"./graphql-REqfkFwC.js";import{o as y,t as b}from"./VList-BDhdlC15.js";import{t as x}from"./VIcon-CaEd7GYo.js";import{An as S,Bn as C,Bt as w,Cn as T,Dn as E,Dt as D,H as O,It as k,Jn as A,Ln as te,Mn as ne,Mt as re,Q as ie,Rt as ae,V as j,Vn as M,Z as N,_n as P,b as F,et as I,ft as L,jn as R,kt as z,l as B,m as V,o as H,pn as U,qt as W,r as G,sn as K,t as q,tt as J,vn as oe,zn as se,zt as ce}from"./index-Dmrs4nxD.js";import{t as Y}from"./VDivider-CazlnPOF.js";import{t as le}from"./EditBulkDialog-GRRWn4-F.js";import{t as X}from"./VCheckboxBtn-BKZYGfRc.js";import{t as ue}from"./VTextField-Bah1I2-y.js";import{t as de}from"./VPagination-CGdil738.js";import{t as fe}from"./VSwitch-Cz2NubM6.js";var Z=v`
  ${v`
  fragment CmsFileFields on File {
    disk
    id
    lang
    mime
    name
    path
    previews
    description
    transcription
    editor
    created_at
    updated_at
    deleted_at
  }
`}
  mutation ($input: FileInput, $file: Upload, $disk: FileDisk) {
    addFile(input: $input, file: $file, disk: $disk) {
      ...CmsFileFields
    }
  }
`,pe=v`
  mutation ($id: [ID!]!, $disk: FileDisk!) {
    relocateFile(id: $id, disk: $disk) {
      disk
      id
      editor
      updated_at
    }
  }
`;function Q(e={}){for(let t of[`previews`,`description`,`transcription`])e[t]=M(e[t]);return delete e.__typename,e}var me=v`
  mutation ($id: [ID!]!) {
    dropFile(id: $id) {
      id
    }
  }
`,he=v`
  mutation ($id: [ID!]!) {
    keepFile(id: $id) {
      id
    }
  }
`,ge=v`
  mutation ($id: [ID!]!) {
    pubFile(id: $id) {
      id
    }
  }
`,_e=v`
  mutation ($id: [ID!]!) {
    purgeFile(id: $id) {
      id
    }
  }
`,ve=v`
  mutation ($id: [ID!]!, $input: FileInput!) {
    bulkFile(id: $id, input: $input) {
      ids
    }
  }
`,ye=v`
  query (
    $filter: FileFilter
    $sort: [QueryFilesSortOrderByClause!]
    $limit: Int!
    $page: Int!
    $trashed: Trashed
    $publish: Publish
  ) {
    files(
      filter: $filter
      sort: $sort
      first: $limit
      page: $page
      trashed: $trashed
      publish: $publish
    ) {
      data {
        disk
        id
        lang
        name
        mime
        path
        previews
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
        byversions_count
      }
      paginatorInfo {
        lastPage
      }
    }
  }
`,be={components:{EditBulkDialog:le},props:{grid:{type:Boolean,default:!1},embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:[`select`],data(){return{items:[],menu:[],checked:new Set,term:``,sort:this.user.getData(`file`,`sort`)||{column:`ID`,order:`DESC`},page:1,last:1,limit:100,actions:!1,editDialog:!1,editIds:[],editSelected:!1,loading:!0,vgrid:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1,protect:!1}},setup(){let e=T(),t=E();return{app:P(),user:t,changes:oe(),messages:e,mdiDotsVertical:J,mdiClose:O,mdiPublish:ce,mdiDelete:N,mdiDeleteRestore:I,mdiDeleteForever:ie,mdiPlus:ae,mdiMagnify:z,mdiViewGridOutline:K,mdiFormatListBulletedSquare:L,mdiMenuDown:re,mdiSort:W,mdiClockOutline:j,mdiLock:D,mdiRefresh:w,mdiPencil:k,debounce:te,fileurl:C,filesrcset:se}},created(){this.searchd=this.debounce(this.search,500),this.vgrid=this.user.getData(`file`,`grid`)??this.grid,this.search(),this.embed||ne(this,`file`,(e,t)=>R(this,e,t))},beforeUnmount(){this.destroyed=!0,S(this),this.items=null,this.menu=null,this.checked=null},activated(){this.sync()},computed:{canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)},order(){return this.sort?.column===`ID`?this.sort?.order===`DESC`?this.$gettext(`latest`):this.$gettext(`oldest`):{BYVERSIONS_COUNT:this.$gettext(`usage`),EDITOR:this.$gettext(`editor`),LANG:this.$gettext(`language`),MIME:this.$gettext(`mime`),NAME:this.$gettext(`name`)}[this.sort?.column]||this.sort?.column||``}},methods:{add(e){if(this.embed||!this.user.can(`file:add`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=[],n=e.target.files||e.dataTransfer.files||[],r=this.protect?`private`:`public`;if(n.length){for(let e of n)t.push(this.$apollo.mutate({mutation:Z,variables:{disk:r,file:e},context:{hasUpload:!0}}).then(e=>{if(e.errors)throw e.errors;let t={...Q(e.data?.addFile),published:!0};return this.items.unshift(t),n.length===1&&this.$emit(`select`,t),t}).catch(t=>{this.messages.add(this.$gettext(`Error adding file %{path}`,{path:e.name})+`:
`+t,`error`),this.$log(`FileListItems::add(): Error adding file`,e,t)}));return Promise.all(t).then(()=>{this.invalidate()})}},drop(e){if(!this.user.can(`file:drop`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:me,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(t=>{this.messages.add(this.$gettext(`Error trashing file`)+`:
`+t,`error`),this.$log(`FileListItems::drop(): Error trashing file`,e,t)})},reload(){this.outdated=!1,this.items=[],this.loading=!0,this.invalidate(),this.search()},patch(e){let t=this.items?.find(t=>t.id===e.id);if(!t)return!1;for(let n in e)n in t&&(t[n]=e[n]);return!0},patchItems(e){let t=new Map(e.map(e=>[e.id,e]));this.items?.forEach(e=>{let n=t.get(e.id);if(n)for(let t in n)t in e&&(e[t]=n[t])})},sync(){let e=this.changes.get(`file`).filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched(`file`,e)},invalidate(){let e=this.$apollo.provider.defaultClient.cache;e.evict({id:`ROOT_QUERY`,fieldName:`files`}),e.gc()},keep(e){if(!this.user.can(`file:keep`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:he,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(t=>{this.messages.add(this.$gettext(`Error restoring file`)+`:
`+t,`error`),this.$log(`FileListItems::keep(): Error restoring file`,e,t)})},publish(e){if(!this.user.can(`file:publish`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id)&&e.id&&!e.published);t.length&&this.$apollo.mutate({mutation:ge,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(t=>{this.messages.add(this.$gettext(`Error publishing file`)+`:
`+t,`error`),this.$log(`FileListItems::publish(): Error publishing file`,e,t)})},purge(e){if(!this.user.can(`file:purge`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=e?[e]:this.items.filter(e=>this.checked.has(e.id));t.length&&this.$apollo.mutate({mutation:_e,variables:{id:t.map(e=>e.id)}}).then(e=>{if(e.errors)throw e.errors;this.invalidate(),this.search()}).catch(t=>{this.messages.add(this.$gettext(`Error purging file`)+`:
`+t,`error`),this.$log(`FileListItems::purge(): Error purging file`,e,t)})},edit(e=null){this.editIds=e?[e.id]:[...this.checked],this.editSelected=!e,this.actions=!1,this.editDialog=this.editIds.length>0},save(e){if(!this.user.can(`file:save`)){this.messages.add(this.$gettext(`Permission denied`),`error`);return}let t=this.editIds,n=this.editSelected?null:new Set(this.checked);if(!(!t.length||e===null))return this.$apollo.mutate({mutation:ve,variables:{id:t,input:{lang:e}}}).then(e=>{if(e.errors)throw e.errors;return this.editIds=[],this.editSelected&&(this.checked=new Set),this.editSelected=!1,this.invalidate(),this.search().then(()=>{n&&(this.checked=n)})}).catch(n=>{this.messages.add(this.$gettext(`Error saving file`)+`:
`+n,`error`),this.$log(`FileListItems::save(): Error saving files`,t,e,n)})},setSort(e,t){this.sort={column:e,order:t}},search(){if(!this.user.can(`file:view`))return this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]);let e=this.filter.publish||null,t=this.filter.trashed||`WITHOUT`,n={...this.filter};delete n.trashed,delete n.publish;for(let e in n)n[e]===null&&delete n[e];return this.term&&(n.any=this.term),this.loading=!0,this.$apollo.query({query:ye,fetchPolicy:`no-cache`,variables:{filter:n,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}).then(e=>{if(e.errors)throw e.errors;let t=e.data.files||{};return this.last=t.paginatorInfo?.lastPage||1,this.items=[...t.data||[]].map(e=>{let t=e.latest?.data?A(e.latest?.data):{...e,previews:A(e.previews)};return t.previews=r(t.previews??{}),Object.assign(t,{disk:e.disk,id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null,usage:e.byversions_count})}),this.checked=new Set,this.loading=!1,this.items}).catch(e=>{this.messages.add(this.$gettext(`Error fetching files`)+`:
`+e,`error`),this.$log(`FileListItems::search(): Error fetching files`,e)})},title(e){let t=[];return e.publish_at&&t.push(`Publish at: `+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked.size>0?this.checked=new Set:this.checked=new Set(this.items.map(e=>e.id))},toggleCheck(e){let t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.file"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData(`file`,`sort`,this.sort),this.search()}},vgrid(e){this.user.saveData(`file`,`grid`,e)}}},xe={class:`header`},Se={class:`bulk`},Ce={class:`btn-actions`},we={key:0},Te={class:`search`},Ee={class:`layout`},De={class:`btn-sort`},Oe=[`onClick`,`title`],ke=[`onClick`,`title`],Ae={key:2,viewBox:`0 0 24 24`,fill:`currentColor`,xmlns:`http://www.w3.org/2000/svg`},je={key:3,fill:`currentColor`,viewBox:`0 0 24 24`,xmlns:`http://www.w3.org/2000/svg`},Me={key:4,width:`24`,height:`24`,viewBox:`0 0 16 16`,fill:`currentColor`,xmlns:`http://www.w3.org/2000/svg`},Ne=[`onClick`,`title`],Pe={class:`item-text`},$={class:`item-head`},Fe={key:0,class:`item-lang`},Ie={class:`item-title`},Le={class:`item-mime item-subtitle`},Re={class:`item-aux`},ze={class:`item-editor`},Be={class:`item-modified item-subtitle`},Ve={key:0,class:`loading`},He={key:1,class:`notfound`},Ue={key:3,class:`btn-group`};function We(r,v,S,C,w,T){let E=d(`EditBulkDialog`);return c(),l(o,null,[a(`div`,xe,[a(`div`,Se,[p(X,{"model-value":w.checked.size>0,onClick:v[0]||=n(e=>T.toggle(),[`stop`]),"aria-label":r.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),a(`span`,Ce,[(c(),m(e(r.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":r.$gettext(`Actions`),modelValue:w.actions,"onUpdate:modelValue":v[8]||=e=>w.actions=e,transition:`scale-transition`,location:`end center`,"max-width":`300`},{activator:t(({props:e})=>[p(V,f(e,{disabled:!T.isChecked||S.embed||!C.user.can(`file:add`),title:r.$gettext(`Actions`),icon:C.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:t(()=>[p(H,null,{default:t(()=>[p(q,{density:`compact`},{default:t(()=>[p(G,null,{default:t(()=>[g(_(r.$gettext(`Actions`)),1)]),_:1}),p(V,{icon:C.mdiClose,"aria-label":r.$gettext(`Close`),onClick:v[1]||=e=>w.actions=!1},null,8,[`icon`,`aria-label`])]),_:1}),p(b,{onClick:v[7]||=e=>w.actions=!1},{default:t(()=>[T.isChecked&&C.user.can(`file:publish`)?(c(),m(y,{key:0},{default:t(()=>[p(V,{"prepend-icon":C.mdiPublish,variant:`text`,onClick:v[2]||=e=>T.publish()},{default:t(()=>[g(_(r.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`])]),_:1})):i(``,!0),T.isChecked&&C.user.can(`file:save`)?(c(),m(y,{key:1},{default:t(()=>[p(V,{"prepend-icon":C.mdiPencil,variant:`text`,onClick:v[3]||=e=>T.edit()},{default:t(()=>[g(_(r.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`])]),_:1})):i(``,!0),T.canTrash&&C.user.can(`file:drop`)?(c(),m(y,{key:2},{default:t(()=>[p(V,{"prepend-icon":C.mdiDelete,variant:`text`,onClick:v[4]||=e=>T.drop()},{default:t(()=>[g(_(r.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`])]),_:1})):i(``,!0),T.isTrashed&&C.user.can(`file:keep`)?(c(),m(y,{key:3},{default:t(()=>[p(V,{"prepend-icon":C.mdiDeleteRestore,variant:`text`,onClick:v[5]||=e=>T.keep()},{default:t(()=>[g(_(r.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`])]),_:1})):i(``,!0),T.isChecked&&C.user.can(`file:purge`)?(c(),m(y,{key:4},{default:t(()=>[p(V,{"prepend-icon":C.mdiDeleteForever,variant:`text`,onClick:v[6]||=e=>T.purge()},{default:t(()=>[g(_(r.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`])]),_:1})):i(``,!0)]),_:1})]),_:1})]),_:1},8,[`aria-label`,`modelValue`]))]),!this.embed&&C.user.can(`file:add`)?(c(),l(`div`,we,[a(`input`,{onChange:v[9]||=e=>T.add(e),ref:`upload`,type:`file`,multiple:``,hidden:``},null,544),p(V,{onClick:v[10]||=e=>r.$refs.upload.click(),title:r.$gettext(`Add files`),disabled:w.loading,icon:C.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):i(``,!0),!S.embed&&C.user.can(`file:add`)?(c(),m(fe,{key:1,modelValue:w.protect,"onUpdate:modelValue":v[11]||=e=>w.protect=e,label:r.$gettext(`Protect with page access`),class:`protect`,color:`primary`,density:`compact`,"hide-details":``},null,8,[`modelValue`,`label`])):i(``,!0)]),a(`div`,Te,[p(ue,{modelValue:w.term,"onUpdate:modelValue":v[12]||=e=>w.term=e,label:r.$gettext(`Search for`),"prepend-inner-icon":C.mdiMagnify,variant:`underlined`,"hide-details":``,clearable:``},null,8,[`modelValue`,`label`,`prepend-inner-icon`])]),a(`div`,Ee,[w.outdated?(c(),m(V,{key:0,onClick:v[13]||=e=>T.reload(),"prepend-icon":C.mdiRefresh,title:r.$gettext(`Updated by another user`),color:`primary`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:t(()=>[g(_(r.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):i(``,!0),p(V,{onClick:v[14]||=e=>T.reload(),title:r.$gettext(`Reload files`),icon:C.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`title`,`icon`]),w.vgrid?i(``,!0):(c(),m(V,{key:1,onClick:v[15]||=e=>w.vgrid=!0,title:r.$gettext(`Grid view`),icon:C.mdiViewGridOutline,class:`btn-grid`,variant:`text`},null,8,[`title`,`icon`])),w.vgrid?(c(),m(V,{key:2,onClick:v[16]||=e=>w.vgrid=!1,title:r.$gettext(`List view`),icon:C.mdiFormatListBulletedSquare,class:`btn-list`,variant:`text`},null,8,[`title`,`icon`])):i(``,!0),a(`span`,De,[p(U,null,{activator:t(({props:e})=>[p(V,f(e,{title:r.$gettext(`Sort by`),"append-icon":C.mdiMenuDown,"prepend-icon":C.mdiSort,variant:`text`}),{default:t(()=>[g(_(T.order),1)]),_:1},16,[`title`,`append-icon`,`prepend-icon`])]),default:t(()=>[p(b,null,{default:t(()=>[p(y,null,{default:t(()=>[p(V,{variant:`text`,onClick:v[17]||=e=>T.setSort(`ID`,`DESC`)},{default:t(()=>[g(_(r.$gettext(`latest`)),1)]),_:1})]),_:1}),p(y,null,{default:t(()=>[p(V,{variant:`text`,onClick:v[18]||=e=>T.setSort(`ID`,`ASC`)},{default:t(()=>[g(_(r.$gettext(`oldest`)),1)]),_:1})]),_:1}),p(y,null,{default:t(()=>[p(V,{variant:`text`,onClick:v[19]||=e=>T.setSort(`NAME`,`ASC`)},{default:t(()=>[g(_(r.$gettext(`name`)),1)]),_:1})]),_:1}),p(y,null,{default:t(()=>[p(V,{variant:`text`,onClick:v[20]||=e=>T.setSort(`MIME`,`ASC`)},{default:t(()=>[g(_(r.$gettext(`mime`)),1)]),_:1})]),_:1}),p(y,null,{default:t(()=>[p(V,{variant:`text`,onClick:v[21]||=e=>T.setSort(`LANG`,`ASC`)},{default:t(()=>[g(_(r.$gettext(`language`)),1)]),_:1})]),_:1}),p(y,null,{default:t(()=>[p(V,{variant:`text`,onClick:v[22]||=e=>T.setSort(`EDITOR`,`ASC`)},{default:t(()=>[g(_(r.$gettext(`editor`)),1)]),_:1})]),_:1}),p(y,null,{default:t(()=>[p(V,{variant:`text`,onClick:v[23]||=e=>T.setSort(`BYVERSIONS_COUNT`,`ASC`)},{default:t(()=>[g(_(r.$gettext(`usage`)),1)]),_:1})]),_:1})]),_:1})]),_:1})])])]),p(b,{class:h([`items`,{grid:w.vgrid,list:!w.vgrid}])},{default:t(()=>[(c(!0),l(o,null,u(w.items,(o,u)=>(c(),m(y,{key:u},{default:t(()=>[p(X,{"model-value":w.checked.has(o.id),"onUpdate:modelValue":e=>T.toggleCheck(o),class:h([{draft:!o.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),(c(),m(e(r.$vuetify.display.xs?`v-dialog`:`v-menu`),{"aria-label":r.$gettext(`Actions`),modelValue:w.menu[u],"onUpdate:modelValue":e=>w.menu[u]=e,transition:`scale-transition`,location:w.vgrid?`start`:`end center`,"max-width":`300`},{activator:t(({props:e})=>[p(V,f({ref_for:!0},e,{title:r.$gettext(`Actions`),icon:C.mdiDotsVertical,class:`btn-actions item-menu`,variant:`text`}),null,16,[`title`,`icon`])]),default:t(()=>[p(H,null,{default:t(()=>[p(q,{density:`compact`},{default:t(()=>[p(G,null,{default:t(()=>[g(_(r.$gettext(`Actions`)),1)]),_:1}),p(V,{icon:C.mdiClose,"aria-label":r.$gettext(`Close`),onClick:e=>w.menu[u]=!1},null,8,[`icon`,`aria-label`,`onClick`])]),_:2},1024),p(b,{onClick:e=>w.menu[u]=!1},{default:t(()=>[ee(p(y,null,{default:t(()=>[p(V,{"prepend-icon":C.mdiPublish,variant:`text`,onClick:e=>T.publish(o)},{default:t(()=>[g(_(r.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1536),[[s,!o.deleted_at&&!o.published&&C.user.can(`file:publish`)]]),!o.deleted_at&&!o.published&&C.user.can(`file:publish`)&&C.user.can(`file:save`)?(c(),m(Y,{key:0})):i(``,!0),C.user.can(`file:save`)?(c(),m(y,{key:1},{default:t(()=>[p(V,{"prepend-icon":C.mdiPencil,variant:`text`,onClick:e=>T.edit(o)},{default:t(()=>[g(_(r.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),C.user.can(`file:save`)?(c(),m(Y,{key:2})):i(``,!0),!o.deleted_at&&C.user.can(`file:drop`)?(c(),m(y,{key:3},{default:t(()=>[p(V,{"prepend-icon":C.mdiDelete,variant:`text`,onClick:e=>T.drop(o)},{default:t(()=>[g(_(r.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),o.deleted_at&&C.user.can(`file:keep`)?(c(),m(y,{key:4},{default:t(()=>[p(V,{"prepend-icon":C.mdiDeleteRestore,variant:`text`,onClick:e=>T.keep(o)},{default:t(()=>[g(_(r.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0),C.user.can(`file:purge`)?(c(),m(y,{key:5},{default:t(()=>[p(V,{"prepend-icon":C.mdiDeleteForever,variant:`text`,onClick:e=>T.purge(o)},{default:t(()=>[g(_(r.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])]),_:2},1024)):i(``,!0)]),_:2},1032,[`onClick`])]),_:2},1024)]),_:2},1032,[`aria-label`,`modelValue`,`onUpdate:modelValue`,`location`])),a(`a`,{href:`#`,class:h([`item-usage`,{notused:!o.usage}]),onClick:n(e=>r.$emit(`select`,o),[`prevent`]),title:T.title(o)},_(o.usage||0),11,Oe),a(`div`,{class:`item-preview`,onClick:e=>r.$emit(`select`,o),title:T.title(o)},[o.mime?.startsWith(`image/`)?(c(),m(B,{key:0,src:C.fileurl(o,Object.values(o.previews)[0]??o.path),srcset:C.filesrcset(o),title:o.name,alt:o.name},null,8,[`src`,`srcset`,`title`,`alt`])):o.mime?.startsWith(`video/`)&&Object.values(o.previews).length?(c(),m(B,{key:1,src:C.fileurl(o,Object.values(o.previews)[0]??``),srcset:C.filesrcset(o),title:o.name,alt:o.name},null,8,[`src`,`srcset`,`title`,`alt`])):o.mime?.startsWith(`video/`)&&!Object.values(o.previews).length?(c(),l(`svg`,Ae,[...v[28]||=[a(`path`,{d:`M10,15L15.19,12L10,9V15M21.56,7.17C21.69,7.64 21.78,8.27 21.84,9.07C21.91,9.87 21.94,10.56 21.94,11.16L22,12C22,14.19 21.84,15.8 21.56,16.83C21.31,17.73 20.73,18.31 19.83,18.56C19.36,18.69 18.5,18.78 17.18,18.84C15.88,18.91 14.69,18.94 13.59,18.94L12,19C7.81,19 5.2,18.84 4.17,18.56C3.27,18.31 2.69,17.73 2.44,16.83C2.31,16.36 2.22,15.73 2.16,14.93C2.09,14.13 2.06,13.44 2.06,12.84L2,12C2,9.81 2.16,8.2 2.44,7.17C2.69,6.27 3.27,5.69 4.17,5.44C4.64,5.31 5.5,5.22 6.82,5.16C8.12,5.09 9.31,5.06 10.41,5.06L12,5C16.19,5 18.8,5.16 19.83,5.44C20.73,5.69 21.31,6.27 21.56,7.17Z`},null,-1)]])):o.mime?.startsWith(`audio/`)?(c(),l(`svg`,je,[...v[29]||=[a(`path`,{d:`M21,3V15.5A3.5,3.5 0 0,1 17.5,19A3.5,3.5 0 0,1 14,15.5A3.5,3.5 0 0,1 17.5,12C18.04,12 18.55,12.12 19,12.34V6.47L9,8.6V17.5A3.5,3.5 0 0,1 5.5,21A3.5,3.5 0 0,1 2,17.5A3.5,3.5 0 0,1 5.5,14C6.04,14 6.55,14.12 7,14.34V6L21,3Z`},null,-1)]])):(c(),l(`svg`,Me,[...v[30]||=[a(`path`,{d:`M7.05 11.885c0 1.415-.548 2.206-1.524 2.206C4.548 14.09 4 13.3 4 11.885c0-1.412.548-2.203 1.526-2.203.976 0 1.524.79 1.524 2.203m-1.524-1.612c-.542 0-.832.563-.832 1.612q0 .133.006.252l1.559-1.143c-.126-.474-.375-.72-.733-.72zm-.732 2.508c.126.472.372.718.732.718.54 0 .83-.563.83-1.614q0-.129-.006-.25zm6.061.624V14h-3v-.595h1.181V10.5h-.05l-1.136.747v-.688l1.19-.786h.69v3.633z`},null,-1),a(`path`,{d:`M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z`},null,-1)]]))],8,ke),a(`a`,{href:`#`,class:h([`item-content`,{trashed:o.deleted_at}]),onClick:n(e=>r.$emit(`select`,o),[`prevent`]),title:T.title(o)},[a(`div`,Pe,[a(`div`,$,[o.lang?(c(),l(`span`,Fe,_(o.lang),1)):i(``,!0),o.publish_at?(c(),m(x,{key:1,class:`publish-at`,icon:C.mdiClockOutline},null,8,[`icon`])):i(``,!0),o.disk===`private`?(c(),m(x,{key:2,class:`item-access`,icon:C.mdiLock,title:r.$gettext(`Protect with page access`)},null,8,[`icon`,`title`])):i(``,!0),a(`span`,Ie,_(o.name),1)]),a(`div`,Le,_(o.mime),1)]),a(`div`,Re,[a(`div`,ze,_(o.editor),1),a(`div`,Be,_(new Date(o.updated_at).toLocaleString()),1)])],10,Ne)]),_:2},1024))),128))]),_:1},8,[`class`]),w.loading?(c(),l(`p`,Ve,[g(_(r.$gettext(`Loading`))+` `,1),v[31]||=a(`svg`,{class:`spinner`,width:`32`,height:`32`,fill:`currentColor`,viewBox:`0 0 24 24`,xmlns:`http://www.w3.org/2000/svg`},[a(`circle`,{class:`spin1`,cx:`4`,cy:`12`,r:`3`}),a(`circle`,{class:`spin1 spin2`,cx:`12`,cy:`12`,r:`3`}),a(`circle`,{class:`spin1 spin3`,cx:`20`,cy:`12`,r:`3`})],-1)])):i(``,!0),!w.loading&&!w.items.length?(c(),l(`p`,He,_(r.$gettext(`No entries found`)),1)):i(``,!0),w.last>1?(c(),m(de,{key:2,modelValue:w.page,"onUpdate:modelValue":v[24]||=e=>w.page=e,length:w.last},null,8,[`modelValue`,`length`])):i(``,!0),!this.embed&&C.user.can(`file:add`)?(c(),l(`div`,Ue,[a(`input`,{onChange:v[25]||=e=>T.add(e),ref:`upload`,type:`file`,multiple:``,hidden:``},null,544),p(V,{onClick:v[26]||=e=>r.$refs.upload.click(),title:r.$gettext(`Add files`),disabled:w.loading,icon:C.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):i(``,!0),p(E,{modelValue:w.editDialog,"onUpdate:modelValue":v[27]||=e=>w.editDialog=e,count:w.editIds.length,onApply:T.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var Ge=F(be,[[`render`,We],[`__scopeId`,`data-v-9936e9cb`]]);export{Q as i,Z as n,pe as r,Ge as t};