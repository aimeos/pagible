import{_ as D,v as g,a3 as C,b as v,w as l,at as T,o as h,a as s,au as q,a5 as N,a9 as S,aa as $,m as y,t as o,ag as k,aY as U,f as n,c as b,F as E,B as I,h as H,aZ as O,a_ as F,a$ as j,a4 as J,r as p,V as L,d as w,M as Z,E as K,a8 as W,ar as z,ap as G,aq as Y,T as Q,as as X,l as f,n as V,i as x,aR as _,p as ee,al as P,ai as te,ah as ae,b0 as se,b1 as A,b2 as le,b3 as M}from"./index-DihRa8yc.js";import{F as ie,H as ne,A as re}from"./ElementListItems-B_lh2_zK.js";import{F as de,G as oe,J as ue,K as me,L as he}from"./mdi-CS7WRiMD.js";const fe={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:C()}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:g`
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
            `,variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;this.element=t.data?.element||{},this.versions=(t.data?.element?.byversions||[]).map(a=>({id:a.versionable_id,type:a.versionable_type.split("\\").at(-1),published:a.published?this.$gettext("yes"):a.publish_at?new Date(a.publish_at).toLocaleDateString():this.$gettext("no")})).filter(a=>this.user.can(a.type.toLowerCase()+":view"))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function ge(e,t,a,i,c,r){return h(),v(T,null,{default:l(()=>[s(q,{class:"scroll"},{default:l(()=>[s(N,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=u=>e.panel=u),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(h(),v(S,{key:0},{default:l(()=>[s($,null,{default:l(()=>[y(o(e.$gettext("Shared elements")),1)]),_:1}),s(k,null,{default:l(()=>[s(U,{density:"comfortable",hover:""},{default:l(()=>[n("thead",null,[n("tr",null,[n("th",null,o(e.$gettext("ID")),1),n("th",null,o(e.$gettext("URL")),1),n("th",null,o(e.$gettext("Name")),1)])]),n("tbody",null,[(h(!0),b(E,null,I(e.element.bypages,u=>(h(),b("tr",{key:u.id},[n("td",null,o(u.id),1),n("td",null,o(u.path),1),n("td",null,o(u.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):H("",!0),e.versions?.length?(h(),v(S,{key:1},{default:l(()=>[s($,null,{default:l(()=>[...t[1]||(t[1]=[y("Versions",-1)])]),_:1}),s(k,null,{default:l(()=>[s(U,{density:"comfortable",hover:""},{default:l(()=>[n("thead",null,[n("tr",null,[n("th",null,o(e.$gettext("ID")),1),n("th",null,o(e.$gettext("Type")),1),n("th",null,o(e.$gettext("Published")),1)])]),n("tbody",null,[(h(!0),b(E,null,I(e.versions,u=>(h(),b("tr",{key:u.id},[n("td",null,o(u.id),1),n("td",null,o(u.type),1),n("td",null,o(u.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):H("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const be=D(fe,[["render",ge],["__scopeId","data-v-3822adde"]]),pe={components:{Fields:ie},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],inject:["locales"],setup(){const e=O(),t=F(),a=j(),i=C();return{app:J(),user:i,languages:e,schemas:t,side:a}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function ve(e,t,a,i,c,r){const u=p("Fields");return h(),v(T,null,{default:l(()=>[s(q,{class:"scroll"},{default:l(()=>[s(L,null,{default:l(()=>[s(w,{cols:"12",md:"6"},{default:l(()=>[s(Z,{ref:"name",readonly:r.readonly,modelValue:a.item.name,"onUpdate:modelValue":t[0]||(t[0]=m=>r.update("name",m)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),s(w,{cols:"12",md:"6"},{default:l(()=>[s(K,{ref:"lang",items:r.locales(!0),readonly:r.readonly,modelValue:a.item.lang,"onUpdate:modelValue":t[1]||(t[1]=m=>r.update("lang",m)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),s(L,null,{default:l(()=>[s(w,{cols:"12"},{default:l(()=>[s(u,{ref:"field",data:a.item.data,"onUpdate:data":t[2]||(t[2]=m=>a.item.data=m),files:a.item.files,"onUpdate:files":t[3]||(t[3]=m=>a.item.files=m),fields:r.fields(a.item.type),readonly:r.readonly,assets:a.assets,type:a.item.type,onError:t[4]||(t[4]=m=>e.$emit("error",m)),onChange:t[5]||(t[5]=m=>e.$emit("update:item",a.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const ye=D(pe,[["render",ve],["__scopeId","data-v-85b116af"]]),Ve={components:{AsideMeta:re,HistoryDialog:ne,ElementDetailRefs:be,ElementDetailItem:ye},inject:["closeView"],props:{item:{type:Object,required:!0}},data:()=>({assets:{},changed:!1,error:!1,publishAt:null,publishing:!1,pubmenu:!1,saving:!1,vhistory:!1,tab:"element"}),setup(){const e=W(),t=z();return{user:C(),drawer:t,messages:e,mdiKeyboardBackspace:he,mdiHistory:me,mdiDatabaseArrowDown:ue,mdiChevronRight:oe,mdiChevronLeft:de}},created(){!this.item?.id||!this.user.can("element:view")||this.$apollo.query({query:g`
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
        `,variables:{id:this.item.id}}).then(e=>{if(e.errors||!e.data.element)throw e;const t=[],a=e.data.element;this.reset(),this.assets={};for(const i of a.latest?.files||a.files||[])this.assets[i.id]={...i,previews:JSON.parse(i.previews||"{}")},t.push(i.id);this.item.files=t}).catch(e=>{this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},methods:{errorUpdated(e){this.error=e},itemUpdated(){this.$emit("update:item",this.item),this.changed=!0},publish(e=null){if(!this.user.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}this.publishing=!0,this.save(!0).then(t=>{t&&this.$apollo.mutate({mutation:g`
              mutation ($id: [ID!]!, $at: DateTime) {
                pubElement(id: $id, at: $at) {
                  id
                }
              }
            `,variables:{id:[this.item.id],at:e?.toISOString()?.substring(0,19)?.replace("T"," ")}}).then(a=>{if(a.errors)throw a.errors;e?(this.item.publish_at=e,this.messages.add(this.$gettext("Element scheduled for publishing at %{date}",{date:e.toLocaleDateString()}),"info")):(this.item.published=!0,this.messages.add(this.$gettext("Element published successfully"),"success")),this.closeView()}).catch(a=>{this.messages.add(this.$gettext("Error publishing element")+`:
`+a,"error"),this.$log("ElementDetail::publish(): Error publishing element",e,a)}).finally(()=>{this.publishing=!1})})},published(){this.publish(this.publishAt),this.pubmenu=!1},reset(){this.changed=!1,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.changed?(this.saving=!0,this.$apollo.mutate({mutation:g`
            mutation ($id: ID!, $input: ElementInput!, $files: [ID!]) {
              saveElement(id: $id, input: $input, files: $files) {
                id
              }
            }
          `,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:this.item.files.filter((t,a,i)=>i.indexOf(t)===a)}}).then(t=>{if(t.errors)throw t.errors;return this.item.published=!1,this.reset(),e||this.messages.add(this.$gettext("Element saved successfully"),"success"),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.changed=!0},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:g`
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
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}}},ce={class:"app-title"},we={class:"menu-content"};function Ee(e,t,a,i,c,r){const u=p("ElementDetailItem"),m=p("ElementDetailRefs"),B=p("AsideMeta"),R=p("HistoryDialog");return h(),b(E,null,[s(G,{elevation:0,density:"compact"},{prepend:l(()=>[s(f,{onClick:t[0]||(t[0]=d=>r.closeView()),title:e.$gettext("Back to list view"),icon:i.mdiKeyboardBackspace},null,8,["title","icon"])]),append:l(()=>[s(f,{onClick:t[1]||(t[1]=d=>e.vhistory=!0),class:V([{hidden:a.item.published&&!e.changed&&!a.item.latest},"no-rtl"]),title:e.$gettext("View history"),icon:i.mdiHistory},null,8,["class","title","icon"]),s(f,{onClick:t[2]||(t[2]=d=>r.save()),loading:e.saving,title:e.$gettext("Save"),class:V([{error:e.error},"menu-save"]),disabled:!e.changed||e.error||!i.user.can("element:save"),variant:!e.changed||e.error||!i.user.can("element:save")?"plain":"flat",color:!e.changed||e.error||!i.user.can("element:save")?"":"blue-darken-1",icon:i.mdiDatabaseArrowDown},null,8,["loading","title","class","disabled","variant","color","icon"]),s(x,{modelValue:e.pubmenu,"onUpdate:modelValue":t[4]||(t[4]=d=>e.pubmenu=d),"close-on-content-click":!1},{activator:l(({props:d})=>[s(f,ee(d,{icon:"",loading:e.publishing,title:e.$gettext("Schedule publishing"),class:[{error:e.error},"menu-publish"],disabled:a.item.published&&!e.changed||e.error||!i.user.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"":"blue-darken-2"}),{default:l(()=>[s(P,null,{default:l(()=>[...t[12]||(t[12]=[n("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[n("path",{d:"M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z"}),n("path",{d:"M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z"})],-1)])]),_:1})]),_:1},16,["loading","title","class","disabled","variant","color"])]),default:l(()=>[n("div",we,[s(_,{modelValue:e.publishAt,"onUpdate:modelValue":t[3]||(t[3]=d=>e.publishAt=d),"hide-header":"","show-adjacent-months":""},null,8,["modelValue"]),s(f,{onClick:r.published,disabled:!e.publishAt||e.error,color:e.publishAt?"primary":"",variant:"text"},{default:l(()=>[y(o(e.$gettext("Publish")),1)]),_:1},8,["onClick","disabled","color"])])]),_:1},8,["modelValue"]),s(f,{icon:"",onClick:t[5]||(t[5]=d=>r.publish()),loading:e.publishing,title:e.$gettext("Publish"),class:V([{error:e.error},"menu-publish"]),disabled:a.item.published&&!e.changed||e.error||!i.user.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!i.user.can("element:publish")?"":"blue-darken-2"},{default:l(()=>[s(P,null,{default:l(()=>[...t[13]||(t[13]=[n("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[n("path",{d:"M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z"})],-1)])]),_:1})]),_:1},8,["loading","title","class","disabled","variant","color"]),s(f,{onClick:t[6]||(t[6]=d=>i.drawer.toggle("aside")),title:e.$gettext("Toggle side menu"),icon:i.drawer.aside?i.mdiChevronRight:i.mdiChevronLeft},null,8,["title","icon"])]),default:l(()=>[s(X,null,{default:l(()=>[n("div",ce,o(e.$gettext("Element"))+": "+o(a.item.name),1)]),_:1})]),_:1}),s(Y,{class:"element-details"},{default:l(()=>[s(te,{onSubmit:t[9]||(t[9]=ae(()=>{},["prevent"]))},{default:l(()=>[s(se,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=d=>e.tab=d)},{default:l(()=>[s(A,{value:"element",class:V({changed:e.changed,error:e.error})},{default:l(()=>[y(o(e.$gettext("Element")),1)]),_:1},8,["class"]),s(A,{value:"refs"},{default:l(()=>[y(o(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),s(le,{modelValue:e.tab,"onUpdate:modelValue":t[8]||(t[8]=d=>e.tab=d),touch:!1},{default:l(()=>[s(M,{value:"element"},{default:l(()=>[s(u,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:a.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),s(M,{value:"refs"},{default:l(()=>[s(m,{item:a.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1})]),_:1}),s(B,{item:a.item},null,8,["item"]),(h(),v(Q,{to:"body"},[s(R,{modelValue:e.vhistory,"onUpdate:modelValue":t[10]||(t[10]=d=>e.vhistory=d),readonly:!i.user.can("element:save"),current:{data:{lang:a.item.lang,type:a.item.type,name:a.item.name,data:a.item.data},files:a.item.files},load:()=>r.versions(a.item.id),onRevert:r.revertVersion,onUse:t[11]||(t[11]=d=>r.use(d))},null,8,["modelValue","readonly","current","load","onRevert"])]))],64)}const $e=D(Ve,[["render",Ee],["__scopeId","data-v-0dfdec00"]]);export{$e as E};
