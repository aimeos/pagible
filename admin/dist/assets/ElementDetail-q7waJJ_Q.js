import{_ as S,L as p,a5 as C,b as V,w as l,aF as q,o as f,a as s,aG as B,a8 as O,ac as $,ad as k,l as c,t as o,aj as U,aH as I,e as r,c as v,F as D,a7 as H,g as L,aQ as R,b6 as j,aR as F,a6 as Z,r as y,V as P,d as E,H as J,aq as W,ab as K,aU as X,aV as Y,aW as z,T as G,aX as Q,k as b,n as w,h as x,$ as _,m as ee,aM as T,aY as te,aI as ae,aN as se,aO as A,aS as le,aT as M}from"./index-Cl50xmP5.js";import{F as ie,H as ne,A as re}from"./ElementListItems-CkZvs1YR.js";import{X as de,Y as oe,Z as ue,I as me,_ as he}from"./mdi-DQo0lCf6.js";const fe={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:C()}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:p`
              query ($id: ID!) {
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
              }
            `,variables:{id:e.id}}).then(t=>{var a,i,u;if(t.errors)throw t.errors;this.element=((a=t.data)==null?void 0:a.element)||{},this.versions=(((u=(i=t.data)==null?void 0:i.element)==null?void 0:u.byversions)||[]).map(n=>({id:n.versionable_id,type:n.versionable_type.split("\\").at(-1),published:n.published?this.$gettext("yes"):n.publish_at?new Date(n.publish_at).toLocaleDateString():this.$gettext("no")})).filter(n=>this.user.can(n.type.toLowerCase()+":view"))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function ge(e,t,a,i,u,n){return f(),V(q,null,{default:l(()=>[s(B,{class:"scroll"},{default:l(()=>[s(O,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=g=>e.panel=g),elevation:"0",multiple:""},{default:l(()=>{var g,m;return[(g=e.element.bypages)!=null&&g.length&&i.user.can("page:view")?(f(),V($,{key:0},{default:l(()=>[s(k,null,{default:l(()=>[c(o(e.$gettext("Shared elements")),1)]),_:1}),s(U,null,{default:l(()=>[s(I,{density:"comfortable",hover:""},{default:l(()=>[r("thead",null,[r("tr",null,[r("th",null,o(e.$gettext("ID")),1),r("th",null,o(e.$gettext("URL")),1),r("th",null,o(e.$gettext("Name")),1)])]),r("tbody",null,[(f(!0),v(D,null,H(e.element.bypages,h=>(f(),v("tr",{key:h.id},[r("td",null,o(h.id),1),r("td",null,o(h.path),1),r("td",null,o(h.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):L("",!0),(m=e.versions)!=null&&m.length?(f(),V($,{key:1},{default:l(()=>[s(k,null,{default:l(()=>[...t[1]||(t[1]=[c("Versions",-1)])]),_:1}),s(U,null,{default:l(()=>[s(I,{density:"comfortable",hover:""},{default:l(()=>[r("thead",null,[r("tr",null,[r("th",null,o(e.$gettext("ID")),1),r("th",null,o(e.$gettext("Type")),1),r("th",null,o(e.$gettext("Published")),1)])]),r("tbody",null,[(f(!0),v(D,null,H(e.versions,h=>(f(),v("tr",{key:h.id},[r("td",null,o(h.id),1),r("td",null,o(h.type),1),r("td",null,o(h.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):L("",!0)]}),_:1},8,["modelValue"])]),_:1})]),_:1})}const be=S(fe,[["render",ge],["__scopeId","data-v-3822adde"]]),pe={components:{Fields:ie},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],inject:["locales"],setup(){const e=R(),t=j(),a=F(),i=C();return{app:Z(),user:i,languages:e,schemas:t,side:a}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){var t,a;return e?(t=this.schemas.content[e])!=null&&t.fields?(a=this.schemas.content[e])==null?void 0:a.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function ve(e,t,a,i,u,n){const g=y("Fields");return f(),V(q,null,{default:l(()=>[s(B,{class:"scroll"},{default:l(()=>[s(P,null,{default:l(()=>[s(E,{cols:"12",md:"6"},{default:l(()=>[s(J,{ref:"name",readonly:n.readonly,modelValue:a.item.name,"onUpdate:modelValue":t[0]||(t[0]=m=>n.update("name",m)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),s(E,{cols:"12",md:"6"},{default:l(()=>[s(W,{ref:"lang",items:n.locales(!0),readonly:n.readonly,modelValue:a.item.lang,"onUpdate:modelValue":t[1]||(t[1]=m=>n.update("lang",m)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),s(P,null,{default:l(()=>[s(E,{cols:"12"},{default:l(()=>[s(g,{ref:"field",data:a.item.data,"onUpdate:data":t[2]||(t[2]=m=>a.item.data=m),files:a.item.files,"onUpdate:files":t[3]||(t[3]=m=>a.item.files=m),fields:n.fields(a.item.type),readonly:n.readonly,assets:a.assets,type:a.item.type,onError:t[4]||(t[4]=m=>e.$emit("error",m)),onChange:t[5]||(t[5]=m=>e.$emit("update:item",a.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const ye=S(pe,[["render",ve],["__scopeId","data-v-85b116af"]]),Ve={components:{AsideMeta:re,HistoryDialog:ne,ElementDetailRefs:be,ElementDetailItem:ye},inject:["closeView"],props:{item:{type:Object,required:!0}},data:()=>({assets:{},changed:!1,error:!1,publishAt:null,publishing:!1,pubmenu:!1,saving:!1,vhistory:!1,tab:"element"}),setup(){const e=K(),t=X();return{user:C(),drawer:t,messages:e,mdiKeyboardBackspace:he,mdiHistory:me,mdiDatabaseArrowDown:ue,mdiChevronRight:oe,mdiChevronLeft:de}},created(){var e;!((e=this.item)!=null&&e.id)||!this.user.can("element:view")||this.$apollo.query({query:p`
          query ($id: ID!) {
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
          }
        `,variables:{id:this.item.id}}).then(t=>{var u;if(t.errors||!t.data.element)throw t;const a=[],i=t.data.element;this.reset(),this.assets={};for(const n of((u=i.latest)==null?void 0:u.files)||i.files||[])this.assets[n.id]={...n,previews:JSON.parse(n.previews||"{}")},a.push(n.id);this.item.files=a}).catch(t=>{this.messages.add(this.$gettext("Error fetching element")+`:
`+t,"error"),this.$log("ElementDetail::watch(item): Error fetching element",t)})},methods:{errorUpdated(e){this.error=e},itemUpdated(){this.$emit("update:item",this.item),this.changed=!0},publish(e=null){if(!this.user.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}this.publishing=!0,this.save(!0).then(t=>{var a,i;t&&this.$apollo.mutate({mutation:p`
              mutation ($id: [ID!]!, $at: DateTime) {
                pubElement(id: $id, at: $at) {
                  id
                }
              }
            `,variables:{id:[this.item.id],at:(i=(a=e==null?void 0:e.toISOString())==null?void 0:a.substring(0,19))==null?void 0:i.replace("T"," ")}}).then(u=>{if(u.errors)throw u.errors;e?(this.item.publish_at=e,this.messages.add(this.$gettext("Element scheduled for publishing at %{date}",{date:e.toLocaleDateString()}),"info")):(this.item.published=!0,this.messages.add(this.$gettext("Element published successfully"),"success")),this.closeView()}).catch(u=>{this.messages.add(this.$gettext("Error publishing element")+`:
`+u,"error"),this.$log("ElementDetail::publish(): Error publishing element",e,u)}).finally(()=>{this.publishing=!1})})},published(){this.publish(this.publishAt),this.pubmenu=!1},reset(){this.changed=!1,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.changed?(this.saving=!0,this.$apollo.mutate({mutation:p`
            mutation ($id: ID!, $input: ElementInput!, $files: [ID!]) {
              saveElement(id: $id, input: $input, files: $files) {
                id
              }
            }
          `,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:this.item.files.filter((t,a,i)=>i.indexOf(t)===a)}}).then(t=>{if(t.errors)throw t.errors;return this.item.published=!1,this.reset(),e||this.messages.add(this.$gettext("Element saved successfully"),"success"),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.changed=!0},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:p`
            query ($id: ID!) {
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
            }
          `,variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(a=>({...a,data:JSON.parse(a.data||"{}"),files:a.files.map(i=>i.id)}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}}},ce={class:"app-title"},we={class:"menu-content"};function Ee(e,t,a,i,u,n){const g=y("ElementDetailItem"),m=y("ElementDetailRefs"),h=y("AsideMeta"),N=y("HistoryDialog");return f(),v(D,null,[s(Y,{elevation:0,density:"compact"},{prepend:l(()=>[s(b,{onClick:t[0]||(t[0]=d=>n.closeView()),title:e.$gettext("Back to list view"),icon:i.mdiKeyboardBackspace},null,8,["title","icon"])]),append:l(()=>[s(b,{onClick:t[1]||(t[1]=d=>e.vhistory=!0),class:w([{hidden:a.item.published&&!e.changed&&!a.item.latest},"no-rtl"]),title:e.$gettext("View history"),icon:i.mdiHistory},null,8,["class","title","icon"]),s(b,{onClick:t[2]||(t[2]=d=>n.save()),loading:e.saving,title:e.$gettext("Save"),class:w([{error:e.error},"menu-save"]),disabled:!e.changed||e.error||!i.user.can("element:save"),variant:!e.changed||e.error||!i.user.can("element:save")?"plain":"flat",color:!e.changed||e.error||!i.user.can("element:save")?"":"blue-darken-1",icon:i.mdiDatabaseArrowDown},null,8,["loading","title","class","disabled","variant","color","icon"]),s(x,{modelValue:e.pubmenu,"onUpdate:modelValue":t[4]||(t[4]=d=>e.pubmenu=d),"close-on-content-click":!1},{activator:l(({props:d})=>[s(b,ee(d,{icon:"",loading:e.publishing,title:e.$gettext("Schedule publishing"),class:[{error:e.error},"menu-publish"],disabled:a.item.published&&!e.changed||e.error||!i.user.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"":"blue-darken-2"}),{default:l(()=>[s(T,null,{default:l(()=>[...t[12]||(t[12]=[r("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[r("path",{d:"M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z"}),r("path",{d:"M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z"})],-1)])]),_:1})]),_:1},16,["loading","title","class","disabled","variant","color"])]),default:l(()=>[r("div",we,[s(_,{modelValue:e.publishAt,"onUpdate:modelValue":t[3]||(t[3]=d=>e.publishAt=d),"hide-header":"","show-adjacent-months":""},null,8,["modelValue"]),s(b,{onClick:n.published,disabled:!e.publishAt||e.error,color:e.publishAt?"primary":"",variant:"text"},{default:l(()=>[c(o(e.$gettext("Publish")),1)]),_:1},8,["onClick","disabled","color"])])]),_:1},8,["modelValue"]),s(b,{icon:"",onClick:t[5]||(t[5]=d=>n.publish()),loading:e.publishing,title:e.$gettext("Publish"),class:w([{error:e.error},"menu-publish"]),disabled:a.item.published&&!e.changed||e.error||!i.user.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"":"blue-darken-2"},{default:l(()=>[s(T,null,{default:l(()=>[...t[13]||(t[13]=[r("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[r("path",{d:"M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z"})],-1)])]),_:1})]),_:1},8,["loading","title","class","disabled","variant","color"]),s(b,{onClick:t[6]||(t[6]=d=>i.drawer.toggle("aside")),title:e.$gettext("Toggle side menu"),icon:i.drawer.aside?i.mdiChevronRight:i.mdiChevronLeft},null,8,["title","icon"])]),default:l(()=>[s(Q,null,{default:l(()=>[r("div",ce,o(e.$gettext("Element"))+": "+o(a.item.name),1)]),_:1})]),_:1}),s(z,{class:"element-details"},{default:l(()=>[s(te,{onSubmit:t[9]||(t[9]=ae(()=>{},["prevent"]))},{default:l(()=>[s(se,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=d=>e.tab=d)},{default:l(()=>[s(A,{value:"element",class:w({changed:e.changed,error:e.error})},{default:l(()=>[c(o(e.$gettext("Element")),1)]),_:1},8,["class"]),s(A,{value:"refs"},{default:l(()=>[c(o(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),s(le,{modelValue:e.tab,"onUpdate:modelValue":t[8]||(t[8]=d=>e.tab=d),touch:!1},{default:l(()=>[s(M,{value:"element"},{default:l(()=>[s(g,{"onUpdate:item":n.itemUpdated,onError:n.errorUpdated,assets:e.assets,item:a.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),s(M,{value:"refs"},{default:l(()=>[s(m,{item:a.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1})]),_:1}),s(h,{item:a.item},null,8,["item"]),(f(),V(G,{to:"body"},[s(N,{modelValue:e.vhistory,"onUpdate:modelValue":t[10]||(t[10]=d=>e.vhistory=d),readonly:!i.user.can("element:save"),current:{data:{lang:a.item.lang,type:a.item.type,name:a.item.name,data:a.item.data},files:a.item.files},load:()=>n.versions(a.item.id),onRevert:n.revertVersion,onUse:t[11]||(t[11]=d=>n.use(d))},null,8,["modelValue","readonly","current","load","onRevert"])]))],64)}const $e=S(Ve,[["render",Ee],["__scopeId","data-v-0dfdec00"]]);export{$e as E};
