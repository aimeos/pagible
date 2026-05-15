import{D as N,l as k,i as u,n as l,an as S,j as f,ak as a,a2 as w,k as v,m,a6 as o,T as A,F as $,a0 as M,V as c,al as _,ag as x,G as D,_ as R,I}from"./tree-BEgJ7c65.js";import{f as C}from"./graphql-CvgRNBEg.js";import{Q as U,d1 as O,dg as B,c5 as F,bk as z,cb as j,bZ as q,bW as Q,c3 as G,bv as H,bx as J,bu as K,c4 as W,bl as Y,by as Z,l as h,z as X,s as ee,m as y,K as P,N as T,x as te,p as se}from"./index-B5TNd1k_.js";import{S as ie}from"./SchemaItems-j5kAqQ7h.js";import{f as le,d as ae}from"./utils-D5Fs6vLJ.js";import{V as L}from"./VCheckboxBtn-Cbxh9YKc.js";import{V as ne}from"./VTextField-BM06_dRQ.js";import{V,b as p}from"./VList-Ctj-K14z.js";import{V as re}from"./VPagination-skdKHhih.js";const de=C`
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
`,oe=C`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,he=C`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,ue=C`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,me=C`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,ce=C`
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
`,pe={components:{SchemaItems:ie},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:["select"],data(){return{items:[],menu:[],checked:new Set,term:"",sort:this.user.getData("element","sort")||{column:"ID",order:"DESC"},page:1,last:1,limit:100,vschemas:!1,actions:!1,loading:!0,trash:!1}},setup(){const t=O();return{user:B(),messages:t,mdiDotsVertical:Z,mdiClose:Y,mdiPublish:W,mdiDelete:K,mdiDeleteRestore:J,mdiDeleteForever:H,mdiPlus:G,mdiMagnify:Q,mdiMenuDown:q,mdiSort:j,mdiClockOutline:z,mdiRefresh:F,debounce:ae}},created(){this.search(),this.searchd=this.debounce(this.search,500)},beforeUnmount(){this.items=null,this.menu=null,this.checked=null},computed:{canTrash(){return this.items.some(t=>this.checked.has(t.id)&&!t.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(t=>this.checked.has(t.id)&&t.deleted_at)}},methods:{add(t){if(this.embed||!this.user.can("element:add")){this.messages.add(this.$gettext("Permission denied"),"error");return}return this.$apollo.mutate({mutation:de,variables:{input:{type:t.type,name:"",data:"{}"}}}).then(e=>{if(e.errors)throw e.errors;const s=e.data?.addElement||{};return s.data=le(s.data),s.published=!0,this.vschemas=!1,this.items.unshift(s),this.$emit("select",s),this.invalidate(),s}).catch(e=>{this.$log("ElementListItems::add(): Error adding shared element",e)})},drop(t){if(!this.user.can("element:drop")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id));e.length&&this.$apollo.mutate({mutation:oe,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error trashing shared element")+`:
`+s,"error"),this.$log("ElementListItems::drop(): Error trashing shared element",e,s)})},reload(){this.items=[],this.loading=!0,this.invalidate(),this.search()},invalidate(){const t=this.$apollo.provider.defaultClient.cache;t.evict({id:"ROOT_QUERY",fieldName:"elements"}),t.gc()},keep(t){if(!this.user.can("element:keep")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id));e.length&&this.$apollo.mutate({mutation:he,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error restoring shared element")+`:
`+s,"error"),this.$log("ElementListItems::keep(): Error restoring shared element",e,s)})},publish(t){if(!this.user.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id)&&s.id&&!s.published);e.length&&this.$apollo.mutate({mutation:ue,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error publishing shared element")+`:
`+s,"error"),this.$log("ElementListItems::publish(): Error publishing shared element",e,s)})},purge(t){if(!this.user.can("element:purge")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id));e.length&&this.$apollo.mutate({mutation:me,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error purging shared element")+`:
`+s,"error"),this.$log("ElementListItems::purge(): Error purging shared element",e,s)})},setSort(t,e){this.sort={column:t,order:e}},search(){if(!this.user.can("element:view"))return this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]);const t=this.filter.publish||null,e=this.filter.trashed||"WITHOUT",s={...this.filter};delete s.publish,delete s.trashed;for(const r in s)s[r]===null&&delete s[r];return this.term&&(s.any=this.term),this.loading=!0,this.$apollo.query({query:ce,fetchPolicy:"no-cache",variables:{filter:s,page:this.page,limit:this.limit,sort:[this.sort],trashed:e,publish:t}}).then(r=>{if(r.errors)throw r.errors;const d=r.data.elements||{};return this.last=d.paginatorInfo?.lastPage||1,this.items=[...d.data||[]].map(n=>{const b=n.latest?.data?JSON.parse(n.latest?.data):{...n,data:JSON.parse(n.data||"{}")};return b.data&&typeof b.data=="object"&&(b.data=N(b.data)),Object.assign(b,{id:n.id,deleted_at:n.deleted_at,created_at:n.created_at,updated_at:n.latest?.created_at||n.updated_at,editor:n.latest?.editor||n.editor,published:n.latest?.published??!0,publish_at:n.latest?.publish_at||null,latestId:n.latest?.id||null})}),this.checked=new Set,this.loading=!1,this.items}).catch(r=>{this.messages.add(this.$gettext("Error fetching shared elements")+`:
`+r,"error"),this.$log("ElementListItems::search(): Error fetching shared element",r)})},title(t){const e=[];return t.publish_at&&e.push("Publish at: "+new Date(t.publish_at).toLocaleDateString()),e.join(`
`)},toggle(){this.checked.size>0?this.checked=new Set:this.checked=new Set(this.items.map(t=>t.id))},toggleCheck(t){const e=new Set(this.checked);e.has(t.id)?e.delete(t.id):e.add(t.id),this.checked=e}},watch:{filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData("element","sort",this.sort),this.search()}}}},ge={class:"header"},fe={class:"bulk"},ve={class:"btn-actions"},be={class:"search"},ke={class:"layout"},Ce={class:"btn-sort"},Ee={class:"actions"},_e={class:"btn-actions"},xe=["onClick","title"],Ve={class:"item-text"},De={class:"item-head"},ye={key:0,class:"item-lang"},Se={class:"item-title"},we={class:"item-type item-subtitle"},$e={class:"item-aux"},Ie={class:"item-editor"},Pe={class:"item-modified item-subtitle"},Te={key:0,class:"loading"},Le={key:1,class:"notfound"},Ne={key:3,class:"btn-group"};function Ae(t,e,s,r,d,n){const b=M("SchemaItems");return c(),k($,null,[u("div",ge,[u("div",fe,[l(L,{"model-value":d.checked.size>0,onClick:e[0]||(e[0]=S(i=>n.toggle(),["stop"])),"aria-label":t.$gettext("Toggle selection")},null,8,["model-value","aria-label"]),u("span",ve,[(c(),f(w(t.$vuetify.display.xs?"v-dialog":"v-menu"),{"aria-label":t.$gettext("Actions"),modelValue:d.actions,"onUpdate:modelValue":e[7]||(e[7]=i=>d.actions=i),transition:"scale-transition",location:"end center","max-width":"300"},{activator:a(({props:i})=>[l(h,D(i,{disabled:!n.isChecked||s.embed||!r.user.can("element:add"),title:t.$gettext("Actions"),icon:r.mdiDotsVertical,variant:"text"}),null,16,["disabled","title","icon"])]),default:a(()=>[l(y,null,{default:a(()=>[l(P,{density:"compact"},{default:a(()=>[l(T,null,{default:a(()=>[m(o(t.$gettext("Actions")),1)]),_:1}),l(h,{icon:r.mdiClose,"aria-label":t.$gettext("Close"),onClick:e[1]||(e[1]=i=>d.actions=!1)},null,8,["icon","aria-label"])]),_:1}),l(V,{onClick:e[6]||(e[6]=i=>d.actions=!1)},{default:a(()=>[_(l(p,null,{default:a(()=>[l(h,{"prepend-icon":r.mdiPublish,variant:"text",onClick:e[2]||(e[2]=i=>n.publish())},{default:a(()=>[m(o(t.$gettext("Publish")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[x,n.isChecked&&r.user.can("element:publish")]]),_(l(p,null,{default:a(()=>[l(h,{"prepend-icon":r.mdiDelete,variant:"text",onClick:e[3]||(e[3]=i=>n.drop())},{default:a(()=>[m(o(t.$gettext("Delete")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[x,n.canTrash&&r.user.can("element:drop")]]),_(l(p,null,{default:a(()=>[l(h,{"prepend-icon":r.mdiDeleteRestore,variant:"text",onClick:e[4]||(e[4]=i=>n.keep())},{default:a(()=>[m(o(t.$gettext("Restore")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[x,n.isTrashed&&r.user.can("element:keep")]]),_(l(p,null,{default:a(()=>[l(h,{"prepend-icon":r.mdiDeleteForever,variant:"text",onClick:e[5]||(e[5]=i=>n.purge())},{default:a(()=>[m(o(t.$gettext("Purge")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[x,n.isChecked&&r.user.can("element:purge")]])]),_:1})]),_:1})]),_:1},8,["aria-label","modelValue"]))]),!this.embed&&this.user.can("element:add")?(c(),f(h,{key:0,onClick:e[8]||(e[8]=i=>d.vschemas=!0),title:t.$gettext("Add element"),disabled:d.loading,icon:r.mdiPlus,class:"btn-add",color:"primary",variant:"tonal"},null,8,["title","disabled","icon"])):v("",!0)]),u("div",be,[l(ne,{modelValue:d.term,"onUpdate:modelValue":e[9]||(e[9]=i=>d.term=i),"prepend-inner-icon":r.mdiMagnify,variant:"underlined",label:t.$gettext("Search for"),"hide-details":"",clearable:""},null,8,["modelValue","prepend-inner-icon","label"])]),u("div",ke,[l(h,{onClick:e[10]||(e[10]=i=>n.reload()),title:t.$gettext("Reload elements"),icon:r.mdiRefresh,class:"btn-reload",variant:"text"},null,8,["title","icon"]),u("span",Ce,[l(X,null,{activator:a(({props:i})=>[l(h,D(i,{title:t.$gettext("Sort by"),"append-icon":r.mdiMenuDown,"prepend-icon":r.mdiSort,variant:"text"}),{default:a(()=>[m(o(d.sort?.column==="ID"?d.sort?.order==="DESC"?t.$gettext("latest"):t.$gettext("oldest"):d.sort?.column||""),1)]),_:1},16,["title","append-icon","prepend-icon"])]),default:a(()=>[l(V,null,{default:a(()=>[l(p,null,{default:a(()=>[l(h,{variant:"text",onClick:e[11]||(e[11]=i=>n.setSort("ID","DESC"))},{default:a(()=>[m(o(t.$gettext("latest")),1)]),_:1})]),_:1}),l(p,null,{default:a(()=>[l(h,{variant:"text",onClick:e[12]||(e[12]=i=>n.setSort("ID","ASC"))},{default:a(()=>[m(o(t.$gettext("oldest")),1)]),_:1})]),_:1}),l(p,null,{default:a(()=>[l(h,{variant:"text",onClick:e[13]||(e[13]=i=>n.setSort("NAME","ASC"))},{default:a(()=>[m(o(t.$gettext("name")),1)]),_:1})]),_:1}),l(p,null,{default:a(()=>[l(h,{variant:"text",onClick:e[14]||(e[14]=i=>n.setSort("TYPE","ASC"))},{default:a(()=>[m(o(t.$gettext("type")),1)]),_:1})]),_:1}),l(p,null,{default:a(()=>[l(h,{variant:"text",onClick:e[15]||(e[15]=i=>n.setSort("EDITOR","ASC"))},{default:a(()=>[m(o(t.$gettext("editor")),1)]),_:1})]),_:1})]),_:1})]),_:1})])])]),l(V,{class:"items"},{default:a(()=>[(c(!0),k($,null,R(d.items,(i,E)=>(c(),f(p,{key:E},{default:a(()=>[u("div",Ee,[l(L,{"model-value":d.checked.has(i.id),"onUpdate:modelValue":g=>n.toggleCheck(i),class:I([{draft:!i.published},"item-check"])},null,8,["model-value","onUpdate:modelValue","class"]),u("span",_e,[(c(),f(w(t.$vuetify.display.xs?"v-dialog":"v-menu"),{"aria-label":t.$gettext("Actions"),modelValue:d.menu[E],"onUpdate:modelValue":g=>d.menu[E]=g,transition:"scale-transition",location:"end center","max-width":"300"},{activator:a(({props:g})=>[l(h,D({ref_for:!0},g,{title:t.$gettext("Actions"),icon:r.mdiDotsVertical,variant:"text"}),null,16,["title","icon"])]),default:a(()=>[l(y,null,{default:a(()=>[l(P,{density:"compact"},{default:a(()=>[l(T,null,{default:a(()=>[m(o(t.$gettext("Actions")),1)]),_:1}),l(h,{icon:r.mdiClose,"aria-label":t.$gettext("Close"),onClick:g=>d.menu[E]=!1},null,8,["icon","aria-label","onClick"])]),_:2},1024),l(V,{onClick:g=>d.menu[E]=!1},{default:a(()=>[_(l(p,null,{default:a(()=>[l(h,{"prepend-icon":r.mdiPublish,variant:"text",onClick:g=>n.publish(i)},{default:a(()=>[m(o(t.$gettext("Publish")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1536),[[x,!i.deleted_at&&!i.published&&this.user.can("element:publish")]]),!i.deleted_at&&this.user.can("element:drop")?(c(),f(p,{key:0},{default:a(()=>[l(h,{"prepend-icon":r.mdiDelete,variant:"text",onClick:g=>n.drop(i)},{default:a(()=>[m(o(t.$gettext("Delete")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0),i.deleted_at&&this.user.can("element:keep")?(c(),f(p,{key:1},{default:a(()=>[l(h,{"prepend-icon":r.mdiDeleteRestore,variant:"text",onClick:g=>n.keep(i)},{default:a(()=>[m(o(t.$gettext("Restore")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0),this.user.can("element:purge")?(c(),f(p,{key:2},{default:a(()=>[l(h,{"prepend-icon":r.mdiDeleteForever,variant:"text",onClick:g=>n.purge(i)},{default:a(()=>[m(o(t.$gettext("Purge")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0)]),_:2},1032,["onClick"])]),_:2},1024)]),_:2},1032,["aria-label","modelValue","onUpdate:modelValue"]))])]),u("a",{href:"#",class:I(["item-content",{trashed:i.deleted_at}]),onClick:S(g=>t.$emit("select",i),["prevent"]),title:n.title(i)},[u("div",Ve,[u("div",De,[i.lang?(c(),k("span",ye,o(i.lang),1)):v("",!0),i.publish_at?(c(),f(te,{key:1,class:"publish-at",icon:r.mdiClockOutline},null,8,["icon"])):v("",!0),u("span",Se,o(i.name||t.$gettext("New")),1)]),u("div",we,o(i.type),1)]),u("div",$e,[u("div",Ie,o(i.editor),1),u("div",Pe,o(new Date(i.updated_at).toLocaleString()),1)])],10,xe)]),_:2},1024))),128))]),_:1}),d.loading?(c(),k("p",Te,[m(o(t.$gettext("Loading"))+" ",1),e[21]||(e[21]=u("svg",{class:"spinner",width:"32",height:"32",fill:"currentColor",viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg"},[u("circle",{class:"spin1",cx:"4",cy:"12",r:"3"}),u("circle",{class:"spin1 spin2",cx:"12",cy:"12",r:"3"}),u("circle",{class:"spin1 spin3",cx:"20",cy:"12",r:"3"})],-1))])):v("",!0),!d.loading&&!d.items.length?(c(),k("p",Le,o(t.$gettext("No entries found")),1)):v("",!0),d.last>1?(c(),f(re,{key:2,modelValue:d.page,"onUpdate:modelValue":e[16]||(e[16]=i=>d.page=i),length:d.last},null,8,["modelValue","length"])):v("",!0),!this.embed&&this.user.can("element:add")?(c(),k("div",Ne,[l(h,{onClick:e[17]||(e[17]=i=>d.vschemas=!0),title:t.$gettext("Add element"),disabled:d.loading,icon:r.mdiPlus,class:"btn-add",color:"primary",variant:"tonal"},null,8,["title","disabled","icon"])])):v("",!0),(c(),f(A,{to:"body"},[l(ee,{modelValue:d.vschemas,"onUpdate:modelValue":e[19]||(e[19]=i=>d.vschemas=i),onAfterLeave:e[20]||(e[20]=i=>d.vschemas=!1),scrollable:"",width:"auto"},{default:a(()=>[l(y,null,{default:a(()=>[l(se,null,{default:a(()=>[l(b,{type:"content",onAdd:e[18]||(e[18]=i=>n.add(i))})]),_:1})]),_:1})]),_:1},8,["modelValue"])]))],64)}const Qe=U(pe,[["render",Ae],["__scopeId","data-v-0e0a2d60"]]);export{Qe as E};
