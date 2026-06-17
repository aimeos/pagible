import{A as R,g as N,j as c,c as k,k as u,b as a,p as S,f,e as l,u as m,x as o,z as _,S as y,y as x,O as P,q as v,F as w,N as U,l as $,T as M}from"./tree-BneADi2F.js";import{g as C}from"./graphql-B4q3OoAp.js";import{_ as O,K as I,P as B,aL as z,aM as F,F as j,E as q,aN as Q,k as Y,aO as H,aP as K,aQ as W,aR as G,aU as X,aV as Z,aW as J,aX as ee,a4 as te,aY as se,D as ie,g as ae,y as D,z as T,A as L,i as h,h as le,j as ne,B as re,C as de,aZ as oe}from"./index-C53sJ6sJ.js";import{S as he}from"./SchemaItems-X1mIX74i.js";import{V as A}from"./VCheckboxBtn-CL43jpUG.js";import{V,a as p}from"./VList-C1SRQdm1.js";import{V as ue}from"./VTextField-D3_NddNY.js";import{V as me}from"./VPagination-DAReFNg9.js";const ce=C`
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
`,pe=C`
  mutation ($id: [ID!]!) {
    dropElement(id: $id) {
      id
    }
  }
`,ge=C`
  mutation ($id: [ID!]!) {
    keepElement(id: $id) {
      id
    }
  }
`,fe=C`
  mutation ($id: [ID!]!) {
    pubElement(id: $id) {
      id
    }
  }
`,ve=C`
  mutation ($id: [ID!]!) {
    purgeElement(id: $id) {
      id
    }
  }
`,be=C`
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
`,ke={components:{SchemaItems:he},props:{embed:{type:Boolean,default:!1},filter:{type:Object,default:()=>({})}},emits:["select"],data(){return{items:[],menu:[],checked:new Set,term:"",sort:this.user.getData("element","sort")||{column:"ID",order:"DESC"},page:1,last:1,limit:100,vschemas:!1,actions:!1,loading:!0,trash:!1,destroyed:!1,echoCleanup:null,echoPromise:null,outdated:!1}},setup(){const e=j(),t=q(),s=Q();return{user:t,changes:s,messages:e,mdiDotsVertical:ae,mdiClose:ie,mdiPublish:se,mdiDelete:te,mdiDeleteRestore:ee,mdiDeleteForever:J,mdiPlus:Z,mdiMagnify:X,mdiMenuDown:G,mdiSort:W,mdiClockOutline:K,mdiRefresh:H,debounce:Y}},created(){this.search(),this.searchd=this.debounce(this.search,500),this.embed||F(this,"element",null,e=>{if(e.editor!==this.user.me?.email){if(e.action!=="saved"){this.outdated=!0;return}this.patch({...oe(e.data),id:e.id,published:e.published,deleted_at:e.deleted_at,publish_at:e.publish_at,updated_at:e.updated_at,editor:e.editor,latest_id:e.latest_id})}})},beforeUnmount(){this.destroyed=!0,z(this),this.items=null,this.menu=null,this.checked=null},activated(){this.sync()},computed:{canTrash(){return this.items.some(e=>this.checked.has(e.id)&&!e.deleted_at)},isChecked(){return this.checked.size>0},isTrashed(){return this.items.some(e=>this.checked.has(e.id)&&e.deleted_at)}},methods:{add(e){if(this.embed||!this.user.can("element:add")){this.messages.add(this.$gettext("Permission denied"),"error");return}return this.$apollo.mutate({mutation:ce,variables:{input:{type:e.type,name:"",data:"{}"}}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.addElement||{};return s.data=B(s.data),s.published=!0,this.vschemas=!1,this.items.unshift(s),this.$emit("select",s),this.invalidate(),s}).catch(t=>{this.$log("ElementListItems::add(): Error adding shared element",t)})},drop(e){if(!this.user.can("element:drop")){this.messages.add(this.$gettext("Permission denied"),"error");return}const t=e?[e]:this.items.filter(s=>this.checked.has(s.id));t.length&&this.$apollo.mutate({mutation:pe,variables:{id:t.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error trashing shared element")+`:
`+s,"error"),this.$log("ElementListItems::drop(): Error trashing shared element",t,s)})},reload(){this.outdated=!1,this.items=[],this.loading=!0,this.invalidate(),this.search()},patch(e){const t=this.items?.find(s=>s.id===e.id);if(!t)return!1;for(const s in e)s in t&&(t[s]=e[s]);return!0},sync(){const e=this.changes.get("element").filter(t=>this.patch(t)).map(t=>t.id);this.changes.patched("element",e)},invalidate(){const e=this.$apollo.provider.defaultClient.cache;e.evict({id:"ROOT_QUERY",fieldName:"elements"}),e.gc()},keep(e){if(!this.user.can("element:keep")){this.messages.add(this.$gettext("Permission denied"),"error");return}const t=e?[e]:this.items.filter(s=>this.checked.has(s.id));t.length&&this.$apollo.mutate({mutation:ge,variables:{id:t.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error restoring shared element")+`:
`+s,"error"),this.$log("ElementListItems::keep(): Error restoring shared element",t,s)})},publish(e){if(!this.user.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}const t=e?[e]:this.items.filter(s=>this.checked.has(s.id)&&s.id&&!s.published);t.length&&this.$apollo.mutate({mutation:fe,variables:{id:t.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error publishing shared element")+`:
`+s,"error"),this.$log("ElementListItems::publish(): Error publishing shared element",t,s)})},purge(e){if(!this.user.can("element:purge")){this.messages.add(this.$gettext("Permission denied"),"error");return}const t=e?[e]:this.items.filter(s=>this.checked.has(s.id));t.length&&this.$apollo.mutate({mutation:ve,variables:{id:t.map(s=>s.id)}}).then(s=>{if(s.errors)throw s.errors;this.invalidate(),this.search()}).catch(s=>{this.messages.add(this.$gettext("Error purging shared element")+`:
`+s,"error"),this.$log("ElementListItems::purge(): Error purging shared element",t,s)})},setSort(e,t){this.sort={column:e,order:t}},search(){if(!this.user.can("element:view"))return this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]);const e=this.filter.publish||null,t=this.filter.trashed||"WITHOUT",s={...this.filter};delete s.publish,delete s.trashed;for(const r in s)s[r]===null&&delete s[r];return this.term&&(s.any=this.term),this.loading=!0,this.$apollo.query({query:be,fetchPolicy:"no-cache",variables:{filter:s,page:this.page,limit:this.limit,sort:[this.sort],trashed:t,publish:e}}).then(r=>{if(r.errors)throw r.errors;const d=r.data.elements||{};return this.last=d.paginatorInfo?.lastPage||1,this.items=[...d.data||[]].map(n=>{const b=n.latest?.data?I(n.latest?.data):{...n,data:I(n.data)};return b.data&&typeof b.data=="object"&&(b.data=R(b.data)),Object.assign(b,{id:n.id,deleted_at:n.deleted_at,created_at:n.created_at,updated_at:n.latest?.created_at||n.updated_at,editor:n.latest?.editor||n.editor,published:n.latest?.published??!0,publish_at:n.latest?.publish_at||null,latest_id:n.latest?.id||null})}),this.checked=new Set,this.loading=!1,this.items}).catch(r=>{this.messages.add(this.$gettext("Error fetching shared elements")+`:
`+r,"error"),this.$log("ElementListItems::search(): Error fetching shared element",r)})},title(e){const t=[];return e.publish_at&&t.push("Publish at: "+new Date(e.publish_at).toLocaleDateString()),t.join(`
`)},toggle(){this.checked.size>0?this.checked=new Set:this.checked=new Set(this.items.map(e=>e.id))},toggleCheck(e){const t=new Set(this.checked);t.has(e.id)?t.delete(e.id):t.add(e.id),this.checked=t}},watch:{"changes.changed.element"(){this.sync()},filter:{deep:!0,handler(){this.search()}},term(){this.searchd()},page(){this.search()},sort:{deep:!0,handler(){this.user.saveData("element","sort",this.sort),this.search()}}}},Ce={class:"header"},Ee={class:"bulk"},_e={class:"btn-actions"},ye={class:"search"},Ve={class:"layout"},xe={class:"btn-sort"},De={class:"actions"},Se={class:"btn-actions"},Pe=["onClick","title"],we={class:"item-text"},$e={class:"item-head"},Ie={key:0,class:"item-lang"},Te={class:"item-title"},Le={class:"item-type item-subtitle"},Ae={class:"item-aux"},Re={class:"item-editor"},Ne={class:"item-modified item-subtitle"},Ue={key:0,class:"loading"},Me={key:1,class:"notfound"},Oe={key:3,class:"btn-group"};function Be(e,t,s,r,d,n){const b=N("SchemaItems");return c(),k(w,null,[u("div",Ce,[u("div",Ee,[a(A,{"model-value":d.checked.size>0,onClick:t[0]||(t[0]=S(i=>n.toggle(),["stop"])),"aria-label":e.$gettext("Toggle selection")},null,8,["model-value","aria-label"]),u("span",_e,[(c(),f(P(e.$vuetify.display.xs?"v-dialog":"v-menu"),{"aria-label":e.$gettext("Actions"),modelValue:d.actions,"onUpdate:modelValue":t[7]||(t[7]=i=>d.actions=i),transition:"scale-transition",location:"end center","max-width":"300"},{activator:l(({props:i})=>[a(h,x(i,{disabled:!n.isChecked||s.embed||!r.user.can("element:add"),title:e.$gettext("Actions"),icon:r.mdiDotsVertical,variant:"text"}),null,16,["disabled","title","icon"])]),default:l(()=>[a(D,null,{default:l(()=>[a(T,{density:"compact"},{default:l(()=>[a(L,null,{default:l(()=>[m(o(e.$gettext("Actions")),1)]),_:1}),a(h,{icon:r.mdiClose,"aria-label":e.$gettext("Close"),onClick:t[1]||(t[1]=i=>d.actions=!1)},null,8,["icon","aria-label"])]),_:1}),a(V,{onClick:t[6]||(t[6]=i=>d.actions=!1)},{default:l(()=>[_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiPublish,variant:"text",onClick:t[2]||(t[2]=i=>n.publish())},{default:l(()=>[m(o(e.$gettext("Publish")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[y,n.isChecked&&r.user.can("element:publish")]]),_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiDelete,variant:"text",onClick:t[3]||(t[3]=i=>n.drop())},{default:l(()=>[m(o(e.$gettext("Delete")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[y,n.canTrash&&r.user.can("element:drop")]]),_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteRestore,variant:"text",onClick:t[4]||(t[4]=i=>n.keep())},{default:l(()=>[m(o(e.$gettext("Restore")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[y,n.isTrashed&&r.user.can("element:keep")]]),_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteForever,variant:"text",onClick:t[5]||(t[5]=i=>n.purge())},{default:l(()=>[m(o(e.$gettext("Purge")),1)]),_:1},8,["prepend-icon"])]),_:1},512),[[y,n.isChecked&&r.user.can("element:purge")]])]),_:1})]),_:1})]),_:1},8,["aria-label","modelValue"]))]),!this.embed&&this.user.can("element:add")?(c(),f(h,{key:0,onClick:t[8]||(t[8]=i=>d.vschemas=!0),title:e.$gettext("Add element"),disabled:d.loading,icon:r.mdiPlus,class:"btn-add",color:"primary",variant:"tonal"},null,8,["title","disabled","icon"])):v("",!0)]),u("div",ye,[a(ue,{modelValue:d.term,"onUpdate:modelValue":t[9]||(t[9]=i=>d.term=i),"prepend-inner-icon":r.mdiMagnify,variant:"underlined",label:e.$gettext("Search for"),"hide-details":"",clearable:""},null,8,["modelValue","prepend-inner-icon","label"])]),u("div",Ve,[d.outdated?(c(),f(h,{key:0,onClick:t[10]||(t[10]=i=>n.reload()),"prepend-icon":r.mdiRefresh,title:e.$gettext("Updated by another user"),color:"primary",variant:"tonal",size:"small",rounded:"lg",class:"btn-outdated"},{default:l(()=>[m(o(e.$gettext("Refresh")),1)]),_:1},8,["prepend-icon","title"])):v("",!0),a(h,{onClick:t[11]||(t[11]=i=>n.reload()),title:e.$gettext("Reload elements"),icon:r.mdiRefresh,class:"btn-reload",variant:"text"},null,8,["title","icon"]),u("span",xe,[a(le,null,{activator:l(({props:i})=>[a(h,x(i,{title:e.$gettext("Sort by"),"append-icon":r.mdiMenuDown,"prepend-icon":r.mdiSort,variant:"text"}),{default:l(()=>[m(o(d.sort?.column==="ID"?d.sort?.order==="DESC"?e.$gettext("latest"):e.$gettext("oldest"):d.sort?.column||""),1)]),_:1},16,["title","append-icon","prepend-icon"])]),default:l(()=>[a(V,null,{default:l(()=>[a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:t[12]||(t[12]=i=>n.setSort("ID","DESC"))},{default:l(()=>[m(o(e.$gettext("latest")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:t[13]||(t[13]=i=>n.setSort("ID","ASC"))},{default:l(()=>[m(o(e.$gettext("oldest")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:t[14]||(t[14]=i=>n.setSort("NAME","ASC"))},{default:l(()=>[m(o(e.$gettext("name")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:t[15]||(t[15]=i=>n.setSort("TYPE","ASC"))},{default:l(()=>[m(o(e.$gettext("type")),1)]),_:1})]),_:1}),a(p,null,{default:l(()=>[a(h,{variant:"text",onClick:t[16]||(t[16]=i=>n.setSort("EDITOR","ASC"))},{default:l(()=>[m(o(e.$gettext("editor")),1)]),_:1})]),_:1})]),_:1})]),_:1})])])]),a(V,{class:"items"},{default:l(()=>[(c(!0),k(w,null,U(d.items,(i,E)=>(c(),f(p,{key:E},{default:l(()=>[u("div",De,[a(A,{"model-value":d.checked.has(i.id),"onUpdate:modelValue":g=>n.toggleCheck(i),class:$([{draft:!i.published},"item-check"])},null,8,["model-value","onUpdate:modelValue","class"]),u("span",Se,[(c(),f(P(e.$vuetify.display.xs?"v-dialog":"v-menu"),{"aria-label":e.$gettext("Actions"),modelValue:d.menu[E],"onUpdate:modelValue":g=>d.menu[E]=g,transition:"scale-transition",location:"end center","max-width":"300"},{activator:l(({props:g})=>[a(h,x({ref_for:!0},g,{title:e.$gettext("Actions"),icon:r.mdiDotsVertical,variant:"text"}),null,16,["title","icon"])]),default:l(()=>[a(D,null,{default:l(()=>[a(T,{density:"compact"},{default:l(()=>[a(L,null,{default:l(()=>[m(o(e.$gettext("Actions")),1)]),_:1}),a(h,{icon:r.mdiClose,"aria-label":e.$gettext("Close"),onClick:g=>d.menu[E]=!1},null,8,["icon","aria-label","onClick"])]),_:2},1024),a(V,{onClick:g=>d.menu[E]=!1},{default:l(()=>[_(a(p,null,{default:l(()=>[a(h,{"prepend-icon":r.mdiPublish,variant:"text",onClick:g=>n.publish(i)},{default:l(()=>[m(o(e.$gettext("Publish")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1536),[[y,!i.deleted_at&&!i.published&&this.user.can("element:publish")]]),!i.deleted_at&&this.user.can("element:drop")?(c(),f(p,{key:0},{default:l(()=>[a(h,{"prepend-icon":r.mdiDelete,variant:"text",onClick:g=>n.drop(i)},{default:l(()=>[m(o(e.$gettext("Delete")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0),i.deleted_at&&this.user.can("element:keep")?(c(),f(p,{key:1},{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteRestore,variant:"text",onClick:g=>n.keep(i)},{default:l(()=>[m(o(e.$gettext("Restore")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0),this.user.can("element:purge")?(c(),f(p,{key:2},{default:l(()=>[a(h,{"prepend-icon":r.mdiDeleteForever,variant:"text",onClick:g=>n.purge(i)},{default:l(()=>[m(o(e.$gettext("Purge")),1)]),_:1},8,["prepend-icon","onClick"])]),_:2},1024)):v("",!0)]),_:2},1032,["onClick"])]),_:2},1024)]),_:2},1032,["aria-label","modelValue","onUpdate:modelValue"]))])]),u("a",{href:"#",class:$(["item-content",{trashed:i.deleted_at}]),onClick:S(g=>e.$emit("select",i),["prevent"]),title:n.title(i)},[u("div",we,[u("div",$e,[i.lang?(c(),k("span",Ie,o(i.lang),1)):v("",!0),i.publish_at?(c(),f(ne,{key:1,class:"publish-at",icon:r.mdiClockOutline},null,8,["icon"])):v("",!0),u("span",Te,o(i.name||e.$gettext("New")),1)]),u("div",Le,o(i.type),1)]),u("div",Ae,[u("div",Re,o(i.editor),1),u("div",Ne,o(new Date(i.updated_at).toLocaleString()),1)])],10,Pe)]),_:2},1024))),128))]),_:1}),d.loading?(c(),k("p",Ue,[m(o(e.$gettext("Loading"))+" ",1),t[22]||(t[22]=u("svg",{class:"spinner",width:"32",height:"32",fill:"currentColor",viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg"},[u("circle",{class:"spin1",cx:"4",cy:"12",r:"3"}),u("circle",{class:"spin1 spin2",cx:"12",cy:"12",r:"3"}),u("circle",{class:"spin1 spin3",cx:"20",cy:"12",r:"3"})],-1))])):v("",!0),!d.loading&&!d.items.length?(c(),k("p",Me,o(e.$gettext("No entries found")),1)):v("",!0),d.last>1?(c(),f(me,{key:2,modelValue:d.page,"onUpdate:modelValue":t[17]||(t[17]=i=>d.page=i),length:d.last},null,8,["modelValue","length"])):v("",!0),!this.embed&&this.user.can("element:add")?(c(),k("div",Oe,[a(h,{onClick:t[18]||(t[18]=i=>d.vschemas=!0),title:e.$gettext("Add element"),disabled:d.loading,icon:r.mdiPlus,class:"btn-add",color:"primary",variant:"tonal"},null,8,["title","disabled","icon"])])):v("",!0),(c(),f(M,{to:"body"},[a(de,{modelValue:d.vschemas,"onUpdate:modelValue":t[20]||(t[20]=i=>d.vschemas=i),onAfterLeave:t[21]||(t[21]=i=>d.vschemas=!1),scrollable:"",width:"auto"},{default:l(()=>[a(D,null,{default:l(()=>[a(re,null,{default:l(()=>[a(b,{type:"content",onAdd:t[19]||(t[19]=i=>n.add(i))})]),_:1})]),_:1})]),_:1},8,["modelValue"])]))],64)}const We=O(ke,[["render",Be],["__scopeId","data-v-afa03c11"]]);export{We as E};
