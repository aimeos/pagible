import{m as A,k,q as u,l as a,z as S,c as f,p as l,r as w,A as v,B as m,C as o,T as N,F as $,j as M,b as c,I as _,X as D,D as V,Q as R,x as I}from"./tree-E0yOiz75.js";import{a as C}from"./graphql-DsaJiMYP.js";import{_ as U,J as B,H as O,aC as F,aD as z,aE as j,aF as q,aG as J,aJ as G,aK as H,aL as Q,aM as Y,Y as K,aN as W,G as X,h as Z,b as h,a as ee,F as te,B as y,C as P,D as T,a7 as se,E as ie}from"./index-JPhTVh7v.js";import{S as ae}from"./SchemaItems-BTCdY9Nh.js";import{f as le,d as ne}from"./utils-CmkUKknr.js";import{V as L}from"./VCheckboxBtn-CczT4OdM.js";import{V as re}from"./VTextField-eHDPVbfL.js";import{V as x,a as p}from"./VList-CVTC9x0Q.js";import{V as de}from"./VPagination-BJVhrYHP.js";const oe=C`
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
`,he=C`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,ue=C`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,me=C`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,ce=C`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,pe=C`
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
`,ge={components:{SchemaItems:ae},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:["select"],data(){return{items:[],menu:[],checked:new Set,term:"",sort:this.user.getData("element","sort")||{column:"ID",order:"DESC"},page:1,last:1,limit:100,vschemas:!1,actions:!1,loading:!0,trash:!1}},setup(){const t=B(),e=O(),s=F();return{user:e,changes:s,messages:t,mdiDotsVertical:Z,mdiClose:X,mdiPublish:W,mdiDelete:K,mdiDeleteRestore:Y,mdiDeleteForever:Q,mdiPlus:H,mdiMagnify:G,mdiMenuDown:J,mdiSort:q,mdiClockOutline:j,mdiRefresh:z,debounce:ne}},created(){this.search(),this.searchd=this.debounce(this.search,500)},beforeUnmount(){this.items=null,this.menu=null,this.checked=null},activated(){this.sync()},computed:{canTrash(){return this.items.some(t=>this.checked.has(t.id)&&!t.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(t=>this.checked.has(t.id)&&t.deleted_at)}},methods:{add(t){if(this.embed||!this.user.can("element:add")){this.messages.add(this.$gettext("Permission denied"),"error");return}return this.$apollo.mutate({mutation:oe,variables:{input:{type:t.type,name:"",data:"{}"}}}).then(e=>{if(e.errors)throw e.errors;const s=e.data?.addElement||{};return s.data=le(s.data),s.published=!0,this.vschemas=!1,this.items.unshift(s),this.$emit("select",s),this.invalidate(),s}).catch(e=>{this.$log("ElementListItems::add(): Error adding shared element",e)})},drop(t){if(!this.user.can("element:drop")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id));e.length&&this.$apollo.mutate({mutation:he,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error trashing shared element")+`:
`+s,"error"),this.$log("ElementListItems::drop(): Error trashing shared element",e,s)})},reload(){this.items=[],this.loading=!0,this.invalidate(),this.search()},patch(t){const e=this.items?.find(s=>s.id===t.id);if(!e)return!1;for(const s in t)s in e&&(e[s]=t[s]);return!0},sync(){const t=this.changes.get("element").filter(e=>this.patch(e)).map(e=>e.id);this.changes.patched("element",t)},invalidate(){const t=this.$apollo.provider.defaultClient.cache;t.evict({id:"ROOT_QUERY",fieldName:"elements"}),t.gc()},keep(t){if(!this.user.can("element:keep")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id));e.length&&this.$apollo.mutate({mutation:ue,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error restoring shared element")+`:
`+s,"error"),this.$log("ElementListItems::keep(): Error restoring shared element",e,s)})},publish(t){if(!this.user.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id)&&s.id&&!s.published);e.length&&this.$apollo.mutate({mutation:me,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error publishing shared element")+`:
`+s,"error"),this.$log("ElementListItems::publish(): Error publishing shared element",e,s)})},purge(t){if(!this.user.can("element:purge")){this.messages.add(this.$gettext("Permission denied"),"error");return}const e=t?[t]:this.items.filter(s=>this.checked.has(s.id));e.length&&this.$apollo.mutate({mutation:ce,variables:{id:e.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error purging shared element")+`:
`+s,"error"),this.$log("ElementListItems::purge(): Error purging shared element",e,s)})},setSort(t,e){this.sort={column:t,order:e}},search(){if(!this.user.can("element:view"))return this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]);const t=this.filter.publish||null,e=this.filter.trashed||"WITHOUT",s={...this.filter};delete s.publish,delete s.trashed;for(const r in s)s[r]===null&&delete s[r];return this.term&&(s.any=this.term),this.loading=!0,this.$apollo.query({query:pe,fetchPolicy:"no-cache",variables:{filter:s,page:this.page,limit:this.limit,sort:[this.sort],trashed:e,publish:t}}).then(r=>{if(r.errors)throw r.errors;const d=r.data.elements||{};return this.last=d.paginatorInfo?.lastPage||1,this.items=[...d.data||[]].map(n=>{const b=n.latest?.data?JSON.parse(n.latest?.data):{...n,data:JSON.parse(n.data||"{}")};return b.data&&typeof b.data=="object"&&(b.data=A(b.data)),Object.assign(b,{id:n.id,deleted_at:n.deleted_at,created_at:n.created_at,updated_at:n.latest?.created_at||n.updated_at,editor:n.latest?.editor||n.editor,published:n.latest?.published??!0,publish_at:n.latest?.publish_at||null,latestId:n.latest?.id||null})}),this.checked=new Set,this.loading=!1,this.items}).catch(r=>{this.messages.add(this.$gettext("Error fetching shared elements")+`:
`+r,"error"),this.$log("ElementListItems::search(): Error fetching shared element",r)})},title(t){const e=[];return t.publish_at&&e.push("Publish at: "+new Date(t.publish_at).toLocaleDateString()),e.join(`
`)},toggle(){this.checked.size>0?this.checked=new Set:this.checked=new Set(this.items.map(t=>t.id))},toggleCheck(t){const e=new Set(this.checked);e.has(t.id)?e.delete(t.id):e.add(t.id),this.checked=e}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData("element","sort",this.sort),this.search()}}}},fe={class:"header"},ve={class:"bulk"},be={class:"btn-actions"},ke={class:"search"},Ce={class:"layout"},Ee={class:"btn-sort"},_e={class:"actions"},De={class:"btn-actions"},xe=["onClick","title"],Ve={class:"item-text"},ye={class:"item-head"},Se={key:0,class:"item-lang"},we={class:"item-title"},$e={class:"item-type item-subtitle"},Ie={class:"item-aux"},Pe={class:"item-editor"},Te={class:"item-modified item-subtitle"},Le={key:0,class:"loading"},Ae={key:1,class:"notfound"},Ne={key:3,class:"btn-group"};function Me(t,e,s,r,d,n){const b=M("SchemaItems");return c(),k($,null,[u("div",fe,[u("div",ve,[a(L,{"model-value":d.checked.size>0,onClick:e[0]||(e[0]=S(i=>n.toggle(),["stop"])),"aria-label":t.$gettext("Toggle selection")},null,8,["model-value","aria-label"]),u("span",be,[(c(),f(w(t.$vuetify.display.xs?"v-dialog":"v-menu"),{"aria-label":t.$gettext("Actions"),modelValue:d.actions,"onUpdate:modelValue":e[7]||(e[7]=i=>d.actions=i),transition:"scale-transition",location:"end center","max-width":"300"},{activator:l(({props:i})=>[a(h,V(i,{disabled:!n.isChecked||s.embed||!r.user.can("element:add"),title:t.$gettext("Actions"),icon:r.mdiDotsVertical,variant:"text"}),null,16,["disabled","title","icon"])]),default:l(()=>[a(y,null,{default:l(()=>[a(P,{density:"compact"},{default:l(()=>[a(T,null,{default:l(()=>[m(o(t.$gettext("Actions")),1)]),_:1}),a(h,{icon:r.mdiClose,"aria-label":t.$gettext("Close"),onClick:e[1]||(e[1]=i=>d.actions=!1)},null,8,["icon","aria-label"])]),_:1}),a(x,{onClick:e[6]||(e[6]=i=>d.actions=!1)},{default:l(()=>[_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiPublish,variant:"text",onClick:e[2]||(e[2]=i=>n.publish())},{default:l(()=>[m(o(t.$gettext("Publish")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[D,n.isChecked&&r.user.can("element:publish")]]),_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiDelete,variant:"text",onClick:e[3]||(e[3]=i=>n.drop())},{default:l(()=>[m(o(t.$gettext("Delete")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[D,n.canTrash&&r.user.can("element:drop")]]),_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteRestore,variant:"text",onClick:e[4]||(e[4]=i=>n.keep())},{default:l(()=>[m(o(t.$gettext("Restore")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[D,n.isTrashed&&r.user.can("element:keep")]]),_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteForever,variant:"text",onClick:e[5]||(e[5]=i=>n.purge())},{default:l(()=>[m(o(t.$gettext("Purge")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[D,n.isChecked&&r.user.can("element:purge")]])]),_:1})]),_:1})]),_:1},8,["aria-label","modelValue"]))]),!this.embed&&this.user.can("element:add")?(c(),f(h,{key:0,onClick:e[8]||(e[8]=i=>d.vschemas=!0),title:t.$gettext("Add element"),disabled:d.loading,icon:r.mdiPlus,class:"btn-add",color:"primary",variant:"tonal"},null,8,["title","disabled","icon"])):v("",!0)]),u("div",ke,[a(re,{modelValue:d.term,"onUpdate:modelValue":e[9]||(e[9]=i=>d.term=i),"prepend-inner-icon":r.mdiMagnify,variant:"underlined",label:t.$gettext("Search for"),"hide-details":"",clearable:""},null,8,["modelValue","prepend-inner-icon","label"])]),u("div",Ce,[a(h,{onClick:e[10]||(e[10]=i=>n.reload()),title:t.$gettext("Reload elements"),icon:r.mdiRefresh,class:"btn-reload",variant:"text"},null,8,["title","icon"]),u("span",Ee,[a(ee,null,{activator:l(({props:i})=>[a(h,V(i,{title:t.$gettext("Sort by"),"append-icon":r.mdiMenuDown,"prepend-icon":r.mdiSort,variant:"text"}),{default:l(()=>[m(o(d.sort?.column==="ID"?d.sort?.order==="DESC"?t.$gettext("latest"):t.$gettext("oldest"):d.sort?.column||""),1)]),_:1},16,["title","append-icon","prepend-icon"])]),default:l(()=>[a(x,null,{default:l(()=>[a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:e[11]||(e[11]=i=>n.setSort("ID","DESC"))},{default:l(()=>[m(o(t.$gettext("latest")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:e[12]||(e[12]=i=>n.setSort("ID","ASC"))},{default:l(()=>[m(o(t.$gettext("oldest")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:e[13]||(e[13]=i=>n.setSort("NAME","ASC"))},{default:l(()=>[m(o(t.$gettext("name")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:e[14]||(e[14]=i=>n.setSort("TYPE","ASC"))},{default:l(()=>[m(o(t.$gettext("type")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:e[15]||(e[15]=i=>n.setSort("EDITOR","ASC"))},{default:l(()=>[m(o(t.$gettext("editor")),1)]),_:1})]),_:1})]),_:1})]),_:1})])])]),a(x,{class:"items"},{default:l(()=>[(c(!0),k($,null,R(d.items,(i,E)=>(c(),f(p,{key:E},{default:l(()=>[u("div",_e,[a(L,{"model-value":d.checked.has(i.id),"onUpdate:modelValue":g=>n.toggleCheck(i),class:I([{draft:!i.published},"item-check"])},null,8,["model-value","onUpdate:modelValue","class"]),u("span",De,[(c(),f(w(t.$vuetify.display.xs?"v-dialog":"v-menu"),{"aria-label":t.$gettext("Actions"),modelValue:d.menu[E],"onUpdate:modelValue":g=>d.menu[E]=g,transition:"scale-transition",location:"end center","max-width":"300"},{activator:l(({props:g})=>[a(h,V({ref_for:!0},g,{title:t.$gettext("Actions"),icon:r.mdiDotsVertical,variant:"text"}),null,16,["title","icon"])]),default:l(()=>[a(y,null,{default:l(()=>[a(P,{density:"compact"},{default:l(()=>[a(T,null,{default:l(()=>[m(o(t.$gettext("Actions")),1)]),_:1}),a(h,{icon:r.mdiClose,"aria-label":t.$gettext("Close"),onClick:g=>d.menu[E]=!1},null,8,["icon","aria-label","onClick"])]),_:2},1024),a(x,{onClick:g=>d.menu[E]=!1},{default:l(()=>[_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiPublish,variant:"text",onClick:g=>n.publish(i)},{default:l(()=>[m(o(t.$gettext("Publish")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1536),[[D,!i.deleted_at&&!i.published&&this.user.can("element:publish")]]),!i.deleted_at&&this.user.can("element:drop")?(c(),f(p,{key:0},{default:l(()=>[a(h,{"prepend-icon":r.mdiDelete,variant:"text",onClick:g=>n.drop(i)},{default:l(()=>[m(o(t.$gettext("Delete")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0),i.deleted_at&&this.user.can("element:keep")?(c(),f(p,{key:1},{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteRestore,variant:"text",onClick:g=>n.keep(i)},{default:l(()=>[m(o(t.$gettext("Restore")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0),this.user.can("element:purge")?(c(),f(p,{key:2},{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteForever,variant:"text",onClick:g=>n.purge(i)},{default:l(()=>[m(o(t.$gettext("Purge")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0)]),_:2},1032,["onClick"])]),_:2},1024)]),_:2},1032,["aria-label","modelValue","onUpdate:modelValue"]))])]),u("a",{href:"#",class:I(["item-content",{trashed:i.deleted_at}]),onClick:S(g=>t.$emit("select",i),["prevent"]),title:n.title(i)},[u("div",Ve,[u("div",ye,[i.lang?(c(),k("span",Se,o(i.lang),1)):v("",!0),i.publish_at?(c(),f(se,{key:1,class:"publish-at",icon:r.mdiClockOutline},null,8,["icon"])):v("",!0),u("span",we,o(i.name||t.$gettext("New")),1)]),u("div",$e,o(i.type),1)]),u("div",Ie,[u("div",Pe,o(i.editor),1),u("div",Te,o(new Date(i.updated_at).toLocaleString()),1)])],10,xe)]),_:2},1024))),128))]),_:1}),d.loading?(c(),k("p",Le,[m(o(t.$gettext("Loading"))+" ",1),e[21]||(e[21]=u("svg",{class:"spinner",width:"32",height:"32",fill:"currentColor",viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg"},[u("circle",{class:"spin1",cx:"4",cy:"12",r:"3"}),u("circle",{class:"spin1 spin2",cx:"12",cy:"12",r:"3"}),u("circle",{class:"spin1 spin3",cx:"20",cy:"12",r:"3"})],-1))])):v("",!0),!d.loading&&!d.items.length?(c(),k("p",Ae,o(t.$gettext("No entries found")),1)):v("",!0),d.last>1?(c(),f(de,{key:2,modelValue:d.page,"onUpdate:modelValue":e[16]||(e[16]=i=>d.page=i),length:d.last},null,8,["modelValue","length"])):v("",!0),!this.embed&&this.user.can("element:add")?(c(),k("div",Ne,[a(h,{onClick:e[17]||(e[17]=i=>d.vschemas=!0),title:t.$gettext("Add element"),disabled:d.loading,icon:r.mdiPlus,class:"btn-add",color:"primary",variant:"tonal"},null,8,["title","disabled","icon"])])):v("",!0),(c(),f(N,{to:"body"},[a(te,{modelValue:d.vschemas,"onUpdate:modelValue":e[19]||(e[19]=i=>d.vschemas=i),onAfterLeave:e[20]||(e[20]=i=>d.vschemas=!1),scrollable:"",width:"auto"},{default:l(()=>[a(y,null,{default:l(()=>[a(ie,null,{default:l(()=>[a(b,{type:"content",onAdd:e[18]||(e[18]=i=>n.add(i))})]),_:1})]),_:1})]),_:1},8,["modelValue"])]))],64)}const Ge=U(ge,[["render",Me],["__scopeId","data-v-9ded86be"]]);export{Ge as E};
