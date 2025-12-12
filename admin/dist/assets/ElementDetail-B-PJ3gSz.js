import{_ as D,L as p,a4 as S,b as V,w as s,aC as q,o as f,a as l,aD as N,a7 as O,d as k,aa as C,ab as I,l as w,t as o,ah as P,aE as L,f as r,c as v,F as $,a6 as T,aM as F,b3 as j,aN as R,a5 as J,r as y,V as H,e as E,I as Z,ao as W,ay as z,aR as K,aS as Q,aT as G,T as X,aU as Y,k as b,n as c,h as x,$ as _,m as ee,aJ as M,aV as te,aF as ae,aK as le,aL as U,aP as se,aQ as A}from"./index-C-hnopMY.js";import{F as ie,H as ne,A as re}from"./ElementListItems-CkHwm2Ah.js";const de={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{auth:S()}},watch:{item:{immediate:!0,handler(e){!e.id||!this.auth.can("element:view")||this.$apollo.query({query:p`query ($id: ID!) {
              element(id: $id) {
                id
                bypages {
                  id
                  path
                  name
                }
                byversions {
                  id
                  versionable_id
                  versionable_type
                  published
                  publish_at
                }
              }
            }`,variables:{id:e.id}}).then(t=>{var a,n,u;if(t.errors)throw t.errors;this.element=((a=t.data)==null?void 0:a.element)||{},this.versions=(((u=(n=t.data)==null?void 0:n.element)==null?void 0:u.byversions)||[]).map(i=>({id:i.versionable_id,type:i.versionable_type.split("\\").at(-1),published:i.published?this.$gettext("yes"):i.publish_at?new Date(i.publish_at).toLocaleDateString():this.$gettext("no")})).filter(i=>this.auth.can(i.type.toLowerCase()+":view"))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function oe(e,t,a,n,u,i){return f(),V(q,null,{default:s(()=>[l(N,{class:"scroll"},{default:s(()=>[l(O,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=g=>e.panel=g),elevation:"0",multiple:""},{default:s(()=>{var g,m;return[(g=e.element.bypages)!=null&&g.length&&n.auth.can("page:view")?(f(),V(C,{key:0},{default:s(()=>[l(I,null,{default:s(()=>[w(o(e.$gettext("Shared elements")),1)]),_:1}),l(P,null,{default:s(()=>[l(L,{density:"comfortable",hover:""},{default:s(()=>[r("thead",null,[r("tr",null,[r("th",null,o(e.$gettext("ID")),1),r("th",null,o(e.$gettext("URL")),1),r("th",null,o(e.$gettext("Name")),1)])]),r("tbody",null,[(f(!0),v($,null,T(e.element.bypages,h=>(f(),v("tr",{key:h.id},[r("td",null,o(h.id),1),r("td",null,o(h.path),1),r("td",null,o(h.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):k("",!0),(m=e.versions)!=null&&m.length?(f(),V(C,{key:1},{default:s(()=>[l(I,null,{default:s(()=>[...t[1]||(t[1]=[w("Versions",-1)])]),_:1}),l(P,null,{default:s(()=>[l(L,{density:"comfortable",hover:""},{default:s(()=>[r("thead",null,[r("tr",null,[r("th",null,o(e.$gettext("ID")),1),r("th",null,o(e.$gettext("Type")),1),r("th",null,o(e.$gettext("Published")),1)])]),r("tbody",null,[(f(!0),v($,null,T(e.versions,h=>(f(),v("tr",{key:h.id},[r("td",null,o(h.id),1),r("td",null,o(h.type),1),r("td",null,o(h.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):k("",!0)]}),_:1},8,["modelValue"])]),_:1})]),_:1})}const ue=D(de,[["render",oe],["__scopeId","data-v-11e22127"]]),me={components:{Fields:ie},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],inject:["locales"],setup(){const e=F(),t=j(),a=R(),n=S();return{app:J(),auth:n,languages:e,schemas:t,side:a}},computed:{readonly(){return!this.auth.can("element:save")}},methods:{fields(e){var t,a;return e?(t=this.schemas.content[e])!=null&&t.fields?(a=this.schemas.content[e])==null?void 0:a.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function he(e,t,a,n,u,i){const g=y("Fields");return f(),V(q,null,{default:s(()=>[l(N,{class:"scroll"},{default:s(()=>[l(H,null,{default:s(()=>[l(E,{cols:"12",md:"6"},{default:s(()=>[l(Z,{ref:"name",readonly:i.readonly,modelValue:a.item.name,"onUpdate:modelValue":t[0]||(t[0]=m=>i.update("name",m)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),l(E,{cols:"12",md:"6"},{default:s(()=>[l(W,{ref:"lang",items:i.locales(!0),readonly:i.readonly,modelValue:a.item.lang,"onUpdate:modelValue":t[1]||(t[1]=m=>i.update("lang",m)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),l(H,null,{default:s(()=>[l(E,{cols:"12"},{default:s(()=>[l(g,{ref:"field",data:a.item.data,"onUpdate:data":t[2]||(t[2]=m=>a.item.data=m),files:a.item.files,"onUpdate:files":t[3]||(t[3]=m=>a.item.files=m),fields:i.fields(a.item.type),readonly:i.readonly,assets:a.assets,type:a.item.type,onError:t[4]||(t[4]=m=>e.$emit("error",m)),onChange:t[5]||(t[5]=m=>e.$emit("update:item",a.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const fe=D(me,[["render",he],["__scopeId","data-v-7d1169fb"]]),ge={components:{AsideMeta:re,HistoryDialog:ne,ElementDetailRefs:ue,ElementDetailItem:fe},inject:["closeView"],props:{item:{type:Object,required:!0}},data:()=>({assets:{},changed:!1,error:!1,publishAt:null,publishing:!1,pubmenu:!1,saving:!1,vhistory:!1,tab:"element"}),setup(){const e=z(),t=K();return{auth:S(),drawer:t,messages:e}},created(){var e;!((e=this.item)!=null&&e.id)||!this.auth.can("element:view")||this.$apollo.query({query:p`query($id: ID!) {
          element(id: $id) {
            id
            files {
              id
              mime
              name
              path
              previews
              updated_at
              editor
            }
            latest {
              id
              published
              data
              editor
              created_at
              files {
                id
                mime
                name
                path
                previews
                updated_at
                editor
              }
            }
          }
        }`,variables:{id:this.item.id}}).then(t=>{var u;if(t.errors||!t.data.element)throw t;const a=[],n=t.data.element;this.reset(),this.assets={};for(const i of((u=n.latest)==null?void 0:u.files)||n.files||[])this.assets[i.id]={...i,previews:JSON.parse(i.previews||"{}")},a.push(i.id);this.item.files=a}).catch(t=>{this.messages.add(this.$gettext("Error fetching element")+`:
`+t,"error"),this.$log("ElementDetail::watch(item): Error fetching element",t)})},methods:{publish(e=null){if(!this.auth.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}this.publishing=!0,this.save(!0).then(t=>{var a,n;t&&this.$apollo.mutate({mutation:p`mutation ($id: [ID!]!, $at: DateTime) {
              pubElement(id: $id, at: $at) {
                id
              }
            }`,variables:{id:[this.item.id],at:(n=(a=e==null?void 0:e.toISOString())==null?void 0:a.substring(0,19))==null?void 0:n.replace("T"," ")}}).then(u=>{if(u.errors)throw u.errors;e?(this.item.publish_at=e,this.messages.add(this.$gettext("Element scheduled for publishing at %{date}",{date:e.toLocaleDateString()}),"info")):(this.item.published=!0,this.messages.add(this.$gettext("Element published successfully"),"success")),this.closeView()}).catch(u=>{this.messages.add(this.$gettext("Error publishing element")+`:
`+u,"error"),this.$log("ElementDetail::publish(): Error publishing element",e,u)}).finally(()=>{this.publishing=!1})})},reset(){this.changed=!1,this.error=!1},save(e=!1){return this.auth.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.changed?(this.saving=!0,this.$apollo.mutate({mutation:p`mutation ($id: ID!, $input: ElementInput!, $files: [ID!]) {
            saveElement(id: $id, input: $input, files: $files) {
              id
            }
          }`,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:this.item.files.filter((t,a,n)=>n.indexOf(t)===a)}}).then(t=>{if(t.errors)throw t.errors;return this.item.published=!1,this.reset(),e||this.messages.add(this.$gettext("Element saved successfully"),"success"),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.changed=!0},versions(e){return this.auth.can("element:view")?e?this.$apollo.query({query:p`query($id: ID!) {
            element(id: $id) {
              id
              versions {
                id
                published
                publish_at
                data
                editor
                created_at
                files {
                  id
                }
              }
            }
          }`,variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(a=>({...a,data:JSON.parse(a.data||"{}"),files:a.files.map(n=>n.id)})).reverse()}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}}},be={class:"app-title"},pe={class:"menu-content"};function ve(e,t,a,n,u,i){const g=y("ElementDetailItem"),m=y("ElementDetailRefs"),h=y("AsideMeta"),B=y("HistoryDialog");return f(),v($,null,[l(Q,{elevation:0,density:"compact"},{prepend:s(()=>[l(b,{onClick:t[0]||(t[0]=d=>i.closeView()),title:e.$gettext("Back to list view"),icon:"mdi-keyboard-backspace"},null,8,["title"])]),append:s(()=>[l(b,{onClick:t[1]||(t[1]=d=>e.vhistory=!0),class:c([{hidden:a.item.published&&!e.changed&&!a.item.latest},"no-rtl"]),title:e.$gettext("View history"),icon:"mdi-history"},null,8,["class","title"]),l(b,{onClick:t[2]||(t[2]=d=>i.save()),loading:e.saving,title:e.$gettext("Save"),class:c([{error:e.error},"menu-save"]),disabled:!e.changed||e.error||!n.auth.can("element:save"),variant:!e.changed||e.error||!n.auth.can("element:save")?"plain":"flat",color:!e.changed||e.error||!n.auth.can("element:save")?"":"blue-darken-1",icon:"mdi-database-arrow-down"},null,8,["loading","title","class","disabled","variant","color"]),l(x,{modelValue:e.pubmenu,"onUpdate:modelValue":t[5]||(t[5]=d=>e.pubmenu=d),"close-on-content-click":!1},{activator:s(({props:d})=>[l(b,ee(d,{icon:"",loading:e.publishing,title:e.$gettext("Schedule publishing"),class:[{error:e.error},"menu-publish"],disabled:a.item.published&&!e.changed||e.error||!n.auth.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!n.auth.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!n.auth.can("element:publish")?"":"blue-darken-2"}),{default:s(()=>[l(M,null,{default:s(()=>[...t[16]||(t[16]=[r("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[r("path",{d:"M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z"}),r("path",{d:"M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z"})],-1)])]),_:1})]),_:1},16,["loading","title","class","disabled","variant","color"])]),default:s(()=>[r("div",pe,[l(_,{modelValue:e.publishAt,"onUpdate:modelValue":t[3]||(t[3]=d=>e.publishAt=d),"hide-header":"","show-adjacent-months":""},null,8,["modelValue"]),l(b,{onClick:t[4]||(t[4]=d=>{i.publish(e.publishAt),e.pubmenu=!1}),disabled:!e.publishAt||e.error,color:e.publishAt?"primary":"",variant:"text"},{default:s(()=>[w(o(e.$gettext("Publish")),1)]),_:1},8,["disabled","color"])])]),_:1},8,["modelValue"]),l(b,{icon:"",onClick:t[6]||(t[6]=d=>i.publish()),loading:e.publishing,title:e.$gettext("Publish"),class:c([{error:e.error},"menu-publish"]),disabled:a.item.published&&!e.changed||e.error||!n.auth.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!n.auth.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!n.auth.can("element:publish")?"":"blue-darken-2"},{default:s(()=>[l(M,null,{default:s(()=>[...t[17]||(t[17]=[r("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[r("path",{d:"M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z"})],-1)])]),_:1})]),_:1},8,["loading","title","class","disabled","variant","color"]),l(b,{onClick:t[7]||(t[7]=d=>n.drawer.toggle("aside")),title:e.$gettext("Toggle side menu"),icon:n.drawer.aside?"mdi-chevron-right":"mdi-chevron-left"},null,8,["title","icon"])]),default:s(()=>[l(Y,null,{default:s(()=>[r("div",be,o(e.$gettext("Element"))+": "+o(a.item.name),1)]),_:1})]),_:1}),l(G,{class:"element-details"},{default:s(()=>[l(te,{onSubmit:t[12]||(t[12]=ae(()=>{},["prevent"]))},{default:s(()=>[l(le,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[8]||(t[8]=d=>e.tab=d)},{default:s(()=>[l(U,{value:"element",class:c({changed:e.changed,error:e.error})},{default:s(()=>[w(o(e.$gettext("Element")),1)]),_:1},8,["class"]),l(U,{value:"refs"},{default:s(()=>[w(o(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),l(se,{modelValue:e.tab,"onUpdate:modelValue":t[11]||(t[11]=d=>e.tab=d),touch:!1},{default:s(()=>[l(A,{value:"element"},{default:s(()=>[l(g,{"onUpdate:item":t[9]||(t[9]=d=>{this.$emit("update:item",a.item),e.changed=!0}),onError:t[10]||(t[10]=d=>e.error=d),assets:e.assets,item:a.item},null,8,["assets","item"])]),_:1}),l(A,{value:"refs"},{default:s(()=>[l(m,{item:a.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1})]),_:1}),l(h,{item:a.item},null,8,["item"]),(f(),V(X,{to:"body"},[l(B,{modelValue:e.vhistory,"onUpdate:modelValue":t[13]||(t[13]=d=>e.vhistory=d),onUse:t[14]||(t[14]=d=>i.use(d)),onRevert:t[15]||(t[15]=d=>{i.use(d),i.reset()}),current:{data:{lang:a.item.lang,type:a.item.type,name:a.item.name,data:a.item.data},files:a.item.files},load:()=>i.versions(a.item.id)},null,8,["modelValue","current","load"])]))],64)}const we=D(ge,[["render",ve],["__scopeId","data-v-75147a4b"]]);export{we as E};
