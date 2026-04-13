import{_ as D,y as g,a9 as S,g as v,w as s,aw as q,o as h,a as i,ax as M,ai as N,aa as k,ab as C,p as y,t as d,ah as $,a_ as U,b as r,c as b,F as E,C as T,i as H,P as R,a$ as j,b0 as F,R as J,r as p,u as I,V as w,O as K,H as Z,a8 as W,ak as z,au as G,as as Q,at as X,T as Y,av as x,m as f,n as V,j as _,aR as ee,q as te,b1 as P,am as ae,f as ie,b2 as se,b3 as A,b4 as le,b5 as L}from"./index-01jQtBHc.js";import{F as re,H as ne,A as de}from"./ElementListItems-BApz0gk2.js";import{l as oe}from"./utils-C05pYWvt.js";import{a as ue,w as me}from"./ai-C4SF2e8U.js";import{F as he,G as fe,J as ge,K as be,L as pe}from"./mdi-DA9g8ejv.js";const ve={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:S()}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:g`
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
            `,variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;this.element=t.data?.element||{},this.versions=(t.data?.element?.byversions||[]).map(a=>({id:a.versionable_id,type:a.versionable_type.split("\\").at(-1),published:a.published?this.$gettext("yes"):a.publish_at?new Date(a.publish_at).toLocaleDateString():this.$gettext("no")})).filter(a=>this.user.can(a.type.toLowerCase()+":view"))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function ye(e,t,a,l,c,o){return h(),v(q,null,{default:s(()=>[i(M,{class:"scroll"},{default:s(()=>[i(N,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=u=>e.panel=u),elevation:"0",multiple:""},{default:s(()=>[e.element.bypages?.length&&l.user.can("page:view")?(h(),v(k,{key:0},{default:s(()=>[i(C,null,{default:s(()=>[y(d(e.$gettext("Shared elements")),1)]),_:1}),i($,null,{default:s(()=>[i(U,{density:"comfortable",hover:""},{default:s(()=>[r("thead",null,[r("tr",null,[r("th",null,d(e.$gettext("ID")),1),r("th",null,d(e.$gettext("URL")),1),r("th",null,d(e.$gettext("Name")),1)])]),r("tbody",null,[(h(!0),b(E,null,T(e.element.bypages,u=>(h(),b("tr",{key:u.id},[r("td",null,d(u.id),1),r("td",null,d(u.path),1),r("td",null,d(u.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):H("",!0),e.versions?.length?(h(),v(k,{key:1},{default:s(()=>[i(C,null,{default:s(()=>[...t[1]||(t[1]=[y("Versions",-1)])]),_:1}),i($,null,{default:s(()=>[i(U,{density:"comfortable",hover:""},{default:s(()=>[r("thead",null,[r("tr",null,[r("th",null,d(e.$gettext("ID")),1),r("th",null,d(e.$gettext("Type")),1),r("th",null,d(e.$gettext("Published")),1)])]),r("tbody",null,[(h(!0),b(E,null,T(e.versions,u=>(h(),b("tr",{key:u.id},[r("td",null,d(u.id),1),r("td",null,d(u.type),1),r("td",null,d(u.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):H("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const Ve=D(ve,[["render",ye],["__scopeId","data-v-7465e16b"]]),ce={components:{Fields:re},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=R(),t=j(),a=F(),l=S();return{app:J(),user:l,languages:e,schemas:t,side:a,locales:oe}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function we(e,t,a,l,c,o){const u=p("Fields");return h(),v(q,null,{default:s(()=>[i(M,{class:"scroll"},{default:s(()=>[i(I,null,{default:s(()=>[i(w,{cols:"12",md:"6"},{default:s(()=>[i(K,{ref:"name",readonly:o.readonly,modelValue:a.item.name,"onUpdate:modelValue":t[0]||(t[0]=m=>o.update("name",m)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),i(w,{cols:"12",md:"6"},{default:s(()=>[i(Z,{ref:"lang",items:l.locales(!0),readonly:o.readonly,modelValue:a.item.lang,"onUpdate:modelValue":t[1]||(t[1]=m=>o.update("lang",m)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),i(I,null,{default:s(()=>[i(w,{cols:"12"},{default:s(()=>[i(u,{ref:"field",data:a.item.data,"onUpdate:data":t[2]||(t[2]=m=>a.item.data=m),files:a.item.files,"onUpdate:files":t[3]||(t[3]=m=>a.item.files=m),fields:o.fields(a.item.type),readonly:o.readonly,assets:a.assets,type:a.item.type,onError:t[4]||(t[4]=m=>e.$emit("error",m)),onChange:t[5]||(t[5]=m=>e.$emit("update:item",a.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const Ee=D(ce,[["render",we],["__scopeId","data-v-f67af8c0"]]),De={components:{AsideMeta:de,HistoryDialog:ne,ElementDetailRefs:Ve,ElementDetailItem:Ee},props:{item:{type:Object,required:!0}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:!1,error:!1,publishAt:null,publishing:!1,pubmenu:!1,saving:!1,vhistory:!1,tab:"element"}),setup(){const e=W(),t=z(),a=G();return{user:S(),drawer:a,messages:t,viewStack:e,mdiKeyboardBackspace:pe,mdiHistory:be,mdiDatabaseArrowDown:ge,mdiChevronRight:fe,mdiChevronLeft:he}},created(){!this.item?.id||!this.user.can("element:view")||this.$apollo.query({query:g`
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
        `,variables:{id:this.item.id}}).then(e=>{if(e.errors||!e.data.element)throw e;const t=[],a=e.data.element;this.reset(),this.assets={};for(const l of a.latest?.files||a.files||[])this.assets[l.id]={...l,previews:JSON.parse(l.previews||"{}")},t.push(l.id);this.item.files=t}).catch(e=>{this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},methods:{errorUpdated(e){this.error=e},itemUpdated(){this.$emit("update:item",this.item),this.changed=!0},publish(e=null){if(!this.user.can("element:publish")){this.messages.add(this.$gettext("Permission denied"),"error");return}this.publishing=!0,this.save(!0).then(t=>{t&&this.$apollo.mutate({mutation:g`
              mutation ($id: [ID!]!, $at: DateTime) {
                pubElement(id: $id, at: $at) {
                  id
                }
              }
            `,variables:{id:[this.item.id],at:e?.toISOString()?.substring(0,19)?.replace("T"," ")}}).then(a=>{if(a.errors)throw a.errors;e?(this.item.publish_at=e,this.messages.add(this.$gettext("Element scheduled for publishing at %{date}",{date:e.toLocaleDateString()}),"info")):(this.item.published=!0,this.messages.add(this.$gettext("Element published successfully"),"success")),this.viewStack.closeView()}).catch(a=>{this.messages.add(this.$gettext("Error publishing element")+`:
`+a,"error"),this.$log("ElementDetail::publish(): Error publishing element",e,a)}).finally(()=>{this.publishing=!1})})},published(){this.publish(this.publishAt),this.pubmenu=!1},reset(){this.changed=!1,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.changed?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:g`
            mutation ($id: ID!, $input: ElementInput!, $files: [ID!]) {
              saveElement(id: $id, input: $input, files: $files) {
                id
              }
            }
          `,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:this.item.files.filter((t,a,l)=>l.indexOf(t)===a)}}).then(t=>{if(t.errors)throw t.errors;return this.item.published=!1,this.reset(),e||this.messages.add(this.$gettext("Element saved successfully"),"success"),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return(e?.title||e?.text||Object.values(e||{}).map(t=>t&&typeof t!="object"&&typeof t!="boolean"?t:null).filter(t=>!!t).join(" - ")).substring(0,100)||""},writeText(e,t=[],a=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),me(e,t,a)},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.changed=!0},translateText(e,t,a=null){return ue(e,t,a||this.item.lang)},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:g`
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
          `,variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(a=>({...a,data:JSON.parse(a.data||"{}"),files:a.files.map(l=>l.id)}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}}},Se={class:"app-title"},ke={class:"menu-content"};function Ce(e,t,a,l,c,o){const u=p("ElementDetailItem"),m=p("ElementDetailRefs"),O=p("AsideMeta"),B=p("HistoryDialog");return h(),b(E,null,[i(Q,{elevation:0,density:"compact",role:"sectionheader","aria-label":e.$gettext("Menu")},{prepend:s(()=>[i(f,{onClick:t[0]||(t[0]=n=>l.viewStack.closeView()),title:e.$gettext("Back to list view"),icon:l.mdiKeyboardBackspace},null,8,["title","icon"])]),append:s(()=>[i(f,{onClick:t[1]||(t[1]=n=>e.vhistory=!0),class:V([{hidden:a.item.published&&!e.changed&&!a.item.latest},"no-rtl"]),title:e.$gettext("View history"),icon:l.mdiHistory},null,8,["class","title","icon"]),i(f,{onClick:t[2]||(t[2]=n=>o.save()),loading:e.saving,title:e.$gettext("Save"),class:V([{error:e.error},"menu-save"]),disabled:!e.changed||e.error||!l.user.can("element:save"),variant:!e.changed||e.error||!l.user.can("element:save")?"plain":"flat",color:!e.changed||e.error||!l.user.can("element:save")?"":"blue-darken-1",icon:l.mdiDatabaseArrowDown},null,8,["loading","title","class","disabled","variant","color","icon"]),i(_,{modelValue:e.pubmenu,"onUpdate:modelValue":t[4]||(t[4]=n=>e.pubmenu=n),"close-on-content-click":!1},{activator:s(({props:n})=>[i(f,te(n,{icon:"",loading:e.publishing,title:e.$gettext("Schedule publishing"),class:[{error:e.error},"menu-publish"],disabled:a.item.published&&!e.changed||e.error||!l.user.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!l.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!l.user.can("element:publish")?"":"blue-darken-2"}),{default:s(()=>[i(P,null,{default:s(()=>[...t[12]||(t[12]=[r("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[r("path",{d:"M2,1V3H16V1H2 M2,10H6V19H12V10H16L9,3L2,10Z"}),r("path",{d:"M16.7 11.4C16.7 11.4 16.61 11.4 16.7 11.4C13.19 11.49 10.4 14.28 10.4 17.7C10.4 21.21 13.19 24 16.7 24S23 21.21 23 17.7 20.21 11.4 16.7 11.4M16.7 22.2C14.18 22.2 12.2 20.22 12.2 17.7S14.18 13.2 16.7 13.2 21.2 15.18 21.2 17.7 19.22 22.2 16.7 22.2M15.6 13.1V17.6L18.84 19.58L19.56 18.5L16.95 16.97V13.1H15.6Z"})],-1)])]),_:1})]),_:1},16,["loading","title","class","disabled","variant","color"])]),default:s(()=>[r("div",ke,[i(ee,{modelValue:e.publishAt,"onUpdate:modelValue":t[3]||(t[3]=n=>e.publishAt=n),"hide-header":"","show-adjacent-months":""},null,8,["modelValue"]),i(f,{onClick:o.published,disabled:!e.publishAt||e.error,color:e.publishAt?"primary":"",variant:"text"},{default:s(()=>[y(d(e.$gettext("Publish")),1)]),_:1},8,["onClick","disabled","color"])])]),_:1},8,["modelValue"]),i(f,{icon:"",onClick:t[5]||(t[5]=n=>o.publish()),loading:e.publishing,title:e.$gettext("Publish"),class:V([{error:e.error},"menu-publish"]),disabled:a.item.published&&!e.changed||e.error||!l.user.can("element:publish"),variant:a.item.published&&!e.changed||e.error||!l.user.can("element:publish")?"plain":"flat",color:a.item.published&&!e.changed||e.error||!l.user.can("element:publish")?"":"blue-darken-2"},{default:s(()=>[i(P,null,{default:s(()=>[...t[13]||(t[13]=[r("svg",{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",fill:"currentColor"},[r("path",{d:"M5,2V4H19V2H5 M5,12H9V21H15V12H19L12,5L5,12Z"})],-1)])]),_:1})]),_:1},8,["loading","title","class","disabled","variant","color"]),i(f,{onClick:t[6]||(t[6]=n=>l.drawer.toggle("aside")),title:e.$gettext("Toggle side menu"),icon:l.drawer.aside?l.mdiChevronRight:l.mdiChevronLeft},null,8,["title","icon"])]),default:s(()=>[i(x,null,{default:s(()=>[r("h1",Se,d(e.$gettext("Element"))+": "+d(a.item.name),1)]),_:1})]),_:1},8,["aria-label"]),i(X,{class:"element-details","aria-label":e.$gettext("Element")},{default:s(()=>[i(ae,{onSubmit:t[9]||(t[9]=ie(()=>{},["prevent"]))},{default:s(()=>[i(se,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=n=>e.tab=n)},{default:s(()=>[i(A,{value:"element",class:V({changed:e.changed,error:e.error})},{default:s(()=>[y(d(e.$gettext("Element")),1)]),_:1},8,["class"]),i(A,{value:"refs"},{default:s(()=>[y(d(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),i(le,{modelValue:e.tab,"onUpdate:modelValue":t[8]||(t[8]=n=>e.tab=n),touch:!1},{default:s(()=>[i(L,{value:"element"},{default:s(()=>[i(u,{"onUpdate:item":o.itemUpdated,onError:o.errorUpdated,assets:e.assets,item:a.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),i(L,{value:"refs"},{default:s(()=>[i(m,{item:a.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1})]),_:1},8,["aria-label"]),i(O,{item:a.item},null,8,["item"]),(h(),v(Y,{to:"body"},[i(B,{modelValue:e.vhistory,"onUpdate:modelValue":t[10]||(t[10]=n=>e.vhistory=n),readonly:!l.user.can("element:save"),current:{data:{lang:a.item.lang,type:a.item.type,name:a.item.name,data:a.item.data},files:a.item.files},load:()=>o.versions(a.item.id),onRevert:o.revertVersion,onUse:t[11]||(t[11]=n=>o.use(n))},null,8,["modelValue","readonly","current","load","onRevert"])]))],64)}const Pe=D(De,[["render",Ce],["__scopeId","data-v-fbacd2ec"]]);export{Pe as E};
