import{_ as C,y as v,a9 as S,g as b,w as l,aw as M,o as h,a as s,ax as O,ai as B,aa as k,ab as I,p as V,t as o,ah as $,a_ as U,b as n,c as y,F as E,C as H,i as D,P as j,a$ as F,b0 as J,R as z,r as g,u as T,V as w,O as K,H as Z,a8 as W,ak as G,au as Q,as as X,at as Y,T as x,av as _,m as f,n as p,j as ee,aR as te,q as ae,b1 as P,am as ie,f as se,b2 as le,b3 as A,b4 as ne,b5 as L}from"./index-CM-IiHiO.js";import{F as re,H as de,C as oe,A as ue,a as me,h as he,s as fe}from"./echo-B9MaT9dq.js";import{l as ge}from"./utils-BpbDQlwM.js";import{a as be,w as pe}from"./ai-CYjO1Kuf.js";import{F as ve,G as ye,J as Ve,K as ce,L as we,M as Ee}from"./mdi-Be4FI9db.js";const De={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:S()}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:v`
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
            `,variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;this.element=t.data?.element||{},this.versions=(t.data?.element?.byversions||[]).map(a=>({id:a.versionable_id,type:a.versionable_type.split("\\").at(-1),published:a.published?this.$gettext("yes"):a.publish_at?new Date(a.publish_at).toLocaleDateString():this.$gettext("no")})).filter(a=>this.user.can(a.type.toLowerCase()+":view"))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function Ce(e,t,a,i,c,d){return h(),b(M,null,{default:l(()=>[s(O,{class:"scroll"},{default:l(()=>[s(B,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=u=>e.panel=u),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(h(),b(k,{key:0},{default:l(()=>[s(I,null,{default:l(()=>[V(o(e.$gettext("Shared elements")),1)]),_:1}),s($,null,{default:l(()=>[s(U,{density:"comfortable",hover:""},{default:l(()=>[n("thead",null,[n("tr",null,[n("th",null,o(e.$gettext("ID")),1),n("th",null,o(e.$gettext("URL")),1),n("th",null,o(e.$gettext("Name")),1)])]),n("tbody",null,[(h(!0),y(E,null,H(e.element.bypages,u=>(h(),y("tr",{key:u.id},[n("td",null,o(u.id),1),n("td",null,o(u.path),1),n("td",null,o(u.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):D("",!0),e.versions?.length?(h(),b(k,{key:1},{default:l(()=>[s(I,null,{default:l(()=>[...t[1]||(t[1]=[V("Versions",-1)])]),_:1}),s($,null,{default:l(()=>[s(U,{density:"comfortable",hover:""},{default:l(()=>[n("thead",null,[n("tr",null,[n("th",null,o(e.$gettext("ID")),1),n("th",null,o(e.$gettext("Type")),1),n("th",null,o(e.$gettext("Published")),1)])]),n("tbody",null,[(h(!0),y(E,null,H(e.versions,u=>(h(),y("tr",{key:u.id},[n("td",null,o(u.id),1),n("td",null,o(u.type),1),n("td",null,o(u.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):D("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const Se=C(De,[["render",Ce],["__scopeId","data-v-7465e16b"]]),ke={components:{Fields:re},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=j(),t=F(),a=J(),i=S();return{app:z(),user:i,languages:e,schemas:t,side:a,locales:ge}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function Ie(e,t,a,i,c,d){const u=g("Fields");return h(),b(M,null,{default:l(()=>[s(O,{class:"scroll"},{default:l(()=>[s(T,null,{default:l(()=>[s(w,{cols:"12",md:"6"},{default:l(()=>[s(K,{ref:"name",readonly:d.readonly,modelValue:a.item.name,"onUpdate:modelValue":t[0]||(t[0]=m=>d.update("name",m)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),s(w,{cols:"12",md:"6"},{default:l(()=>[s(Z,{ref:"lang",items:i.locales(!0),readonly:d.readonly,modelValue:a.item.lang,"onUpdate:modelValue":t[1]||(t[1]=m=>d.update("lang",m)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),s(T,null,{default:l(()=>[s(w,{cols:"12"},{default:l(()=>[s(u,{ref:"field",data:a.item.data,"onUpdate:data":t[2]||(t[2]=m=>a.item.data=m),files:a.item.files,"onUpdate:files":t[3]||(t[3]=m=>a.item.files=m),fields:d.fields(a.item.type),readonly:d.readonly,assets:a.assets,type:a.item.type,onError:t[4]||(t[4]=m=>e.$emit("error",m)),onChange:t[5]||(t[5]=m=>e.$emit("update:item",a.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const $e=C(ke,[["render",Ie],["__scopeId","data-v-f67af8c0"]]),Ue={components:{AsideMeta:ue,ChangesDialog:oe,HistoryDialog:de,ElementDetailRefs:Se,ElementDetailItem:$e},props:{item:{type:Object,required:!0}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,dirty:!1,echoCleanup:null,error:!1,latestId:null,publishAt:null,publishing:!1,pubmenu:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=W(),t=G(),a=Q();return{user:S(),drawer:a,messages:t,viewStack:e,mdiKeyboardBackspace:Ee,mdiHistory:we,mdiDatabaseArrowDown:ce,mdiSwapHorizontal:Ve,mdiChevronRight:ye,mdiChevronLeft:ve}},created(){!this.item?.id||!this.user.can("element:view")||this.$apollo.query({query:v`
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
        `,variables:{id:this.item.id}}).then(e=>{if(e.errors||!e.data.element)throw e;const t=[],a=e.data.element;this.reset(),this.latestId=a.latest?.id,this.assets={};for(const i of a.latest?.files||a.files||[])this.assets[i.id]={...i,previews:JSON.parse(i.previews||"{}")},t.push(i.id);this.item.files=t,fe("element",this.item.id,i=>{!this.dirty&&this.user.can("element:view")&&i.editor!==this.user.me?.email&&(this.latestId=i.versionId,Object.assign(this.item,i.data))}).then(i=>{this.echoCleanup=i})}).catch(e=>{this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.echoCleanup?.()},computed:{hasConflict(){return he(this.changed)}},methods:{errorUpdated(e){this.error=e},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},publish(e=null){if(!this.user.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}this.publishing=!0,this.save(!0).then(t=>{!t||this.changed||this.$apollo.mutate({mutation:v`
              mutation ($id: [ID!]!, $at: DateTime) {
                pubElement(id: $id, at: $at) {
                  id
                }
              }
            `,variables:{id:[this.item.id],at:e?.toISOString()?.substring(0,19)?.replace("T"," ")}}).then(a=>{if(a.errors)throw a.errors;e?(this.item.publish_at=e,this.messages.add(this.$gettext("Element scheduled for publishing at %{date}",{date:e.toLocaleDateString()}),"info")):(this.item.published=!0,this.messages.add(this.$gettext("Element published successfully"),"success")),this.viewStack.closeView()}).catch(a=>{this.messages.add(this.$gettext("Error publishing element")+`:
`+a,"error"),this.$log("ElementDetail::publish(): Error publishing element",e,a)}).finally(()=>{this.publishing=!1})})},published(){this.publish(this.publishAt),this.pubmenu=!1},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:v`
            mutation ($id: ID!, $input: ElementInput!, $files: [ID!], $latestId: ID) {
              saveElement(id: $id, input: $input, files: $files, latestId: $latestId) {
                id
                latest { id }
                changed
              }
            }
          `,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:this.item.files.filter((t,a,i)=>i.indexOf(t)===a),latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const a=t.data?.saveElement,i=a?.changed?JSON.parse(a.changed):null;return(i?.latest?.id||a?.latest?.id)&&(this.latestId=i?.latest?.id??a.latest.id),me(this,i,this.$gettext("Element saved successfully"),e),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return(e?.title||e?.text||Object.values(e||{}).map(t=>t&&typeof t!="object"&&typeof t!="boolean"?t:null).filter(t=>!!t).join(" - ")).substring(0,100)||""},writeText(e,t=[],a=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),pe(e,t,a)},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.dirty=!0},translateText(e,t,a=null){return be(e,t,a||this.item.lang)},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:v`
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
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}}},He={class:"app-title"},Te={class:"menu-content"};function Pe(e,t,a,i,c,d){const u=g("ElementDetailItem"),m=g("ElementDetailRefs"),q=g("AsideMeta"),R=g("HistoryDialog"),N=g("ChangesDialog");return h(),y(E,null,[s(X,{elevation:0,density:"compact",role:"sectionheader","aria-label":e.$gettext("Menu")},{prepend:l(()=>[s(f,{onClick:t[0]||(t[0]=r=>i.viewStack.closeView()),title:e.$gettext("Back to list view"),icon:i.mdiKeyboardBackspace},null,8,["title","icon"])]),append:l(()=>[s(f,{onClick:t[1]||(t[1]=r=>e.vhistory=!0),class:p([{hidden:a.item.published&&!e.dirty&&!a.item.latest},"no-rtl"]),title:e.$gettext("View history"),icon:i.mdiHistory},null,8,["class","title","icon"]),e.changed?(h(),b(f,{key:0,onClick:t[2]||(t[2]=r=>e.vchanged=!0),class:p([{error:d.hasConflict},"menu-changed"]),title:e.$gettext("View merge changes"),icon:i.mdiSwapHorizontal},null,8,["class","title","icon"])):D("",!0),s(f,{onClick:t[3]||(t[3]=r=>d.save()),loading:e.saving,title:e.$gettext("Save"),class:p([{error:e.error},"menu-save"]),disabled:!e.dirty||e.error||!i.user.can("element:save"),variant:!e.dirty||e.error||!i.user.can("element:save")?"plain":"flat",color:d.hasConflict?"warning":!e.dirty||e.error||!i.user.can("element:save")?"":"blue-darken-1",icon:i.mdiDatabaseArrowDown},null,8,["loading","title","class","disabled","variant","color","icon"]),s(ee,{modelValue:e.pubmenu,"onUpdate:modelValue":t[5]||(t[5]=r=>e.pubmenu=r),"close-on-content-click":!1},{activator:l(({props:r})=>[s(f,ae(r,{icon:"",loading:e.publishing,title:e.$gettext("Schedule publishing"),class:[{error:e.error},"menu-publish"],disabled:a.item.published&&!e.dirty||e.error||!i.user.can("element:publish"),variant:a.item.published&&!e.dirty||e.error||!i.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.dirty||e.error||!i.user.can("element:publish")?"":"blue-darken-2"}),{default:l(()=>[s(P,null,{default:l(()=>[...t[15]||(t[15]=[n("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[n("path",{d:"M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z"}),n("path",{d:"M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z"})],-1)])]),_:1})]),_:1},16,["loading","title","class","disabled","variant","color"])]),default:l(()=>[n("div",Te,[s(te,{modelValue:e.publishAt,"onUpdate:modelValue":t[4]||(t[4]=r=>e.publishAt=r),"hide-header":"","show-adjacent-months":""},null,8,["modelValue"]),s(f,{onClick:d.published,disabled:!e.publishAt||e.error,color:e.publishAt?"primary":"",variant:"text"},{default:l(()=>[V(o(e.$gettext("Publish")),1)]),_:1},8,["onClick","disabled","color"])])]),_:1},8,["modelValue"]),s(f,{icon:"",onClick:t[6]||(t[6]=r=>d.publish()),loading:e.publishing,title:e.$gettext("Publish"),class:p([{error:e.error},"menu-publish"]),disabled:a.item.published&&!e.dirty||e.error||!i.user.can("element:publish"),variant:a.item.published&&!e.dirty||e.error||!i.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.dirty||e.error||!i.user.can("element:publish")?"":"blue-darken-2"},{default:l(()=>[s(P,null,{default:l(()=>[...t[16]||(t[16]=[n("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[n("path",{d:"M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z"})],-1)])]),_:1})]),_:1},8,["loading","title","class","disabled","variant","color"]),s(f,{onClick:t[7]||(t[7]=r=>i.drawer.toggle("aside")),title:e.$gettext("Toggle side menu"),icon:i.drawer.aside?i.mdiChevronRight:i.mdiChevronLeft},null,8,["title","icon"])]),default:l(()=>[s(_,null,{default:l(()=>[n("h1",He,o(e.$gettext("Element"))+": "+o(a.item.name),1)]),_:1})]),_:1},8,["aria-label"]),s(Y,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[s(ie,{onSubmit:t[10]||(t[10]=se(()=>{},["prevent"]))},{default:l(()=>[s(le,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[8]||(t[8]=r=>e.tab=r)},{default:l(()=>[s(A,{value:"element",class:p({changed:e.dirty,error:e.error})},{default:l(()=>[V(o(e.$gettext("Element")),1)]),_:1},8,["class"]),s(A,{value:"refs"},{default:l(()=>[V(o(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),s(ne,{modelValue:e.tab,"onUpdate:modelValue":t[9]||(t[9]=r=>e.tab=r),touch:!1},{default:l(()=>[s(L,{value:"element"},{default:l(()=>[s(u,{"onUpdate:item":d.itemUpdated,onError:d.errorUpdated,assets:e.assets,item:a.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),s(L,{value:"refs"},{default:l(()=>[s(m,{item:a.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1})]),_:1},8,["aria-label"]),s(q,{item:a.item},null,8,["item"]),(h(),b(x,{to:"body"},[s(R,{modelValue:e.vhistory,"onUpdate:modelValue":t[11]||(t[11]=r=>e.vhistory=r),readonly:!i.user.can("element:save"),current:{data:{lang:a.item.lang,type:a.item.type,name:a.item.name,data:a.item.data},files:a.item.files},load:()=>d.versions(a.item.id),onRevert:d.revertVersion,onUse:t[12]||(t[12]=r=>d.use(r))},null,8,["modelValue","readonly","current","load","onRevert"]),s(N,{modelValue:e.vchanged,"onUpdate:modelValue":t[13]||(t[13]=r=>e.vchanged=r),changed:e.changed,targets:{data:a.item},onResolve:t[14]||(t[14]=r=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const Re=C(Ue,[["render",Pe],["__scopeId","data-v-4f1a2a3b"]]);export{Re as E};
