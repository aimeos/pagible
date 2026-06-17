const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-D47zox3E.js","./index-C53sJ6sJ.js","./tree-BneADi2F.js","./graphql-B4q3OoAp.js","./shared-C1gvLhAf.js","./index-B9kvflem.css","./VChip-CXzD2lkW.js","./VSlideGroup-Cz8sD7EC.js","./VSlideGroup-Dco48V-a.css","./VChip-BfxGbTdl.css","./ChangesDialog-C1PZAJRP.css","./HistoryDialog-DEMS1EAd.js","./VCheckbox-Be5Kds0S.js","./VCheckboxBtn-CL43jpUG.js","./VSelectionControl-C-Jhd07u.js","./VInput-DgH-pXhD.js","./VInput-DOMR_3Gs.css","./VSelectionControl-eTkllCWw.css","./VCheckbox-DXrFno_w.css","./HistoryDialog-B8k5lONJ.css","./ai-BDifcPYQ.js","./audio-CvQuggF0.js"])))=>i.map(i=>d[i]);
import{_ as $,E as S,c7 as H,L as J,ch as L,R as W,ci as K,P as V,n as c,U as Q,K as T,aL as Z,aM as G,cj as X,F as Y,Q as x,aN as ee,aZ as te,V as se}from"./index-C53sJ6sJ.js";import{g as E}from"./graphql-B4q3OoAp.js";import{D as ie,A as ae,a as le,p as re,b as ne,h as de}from"./publish-ByVDpmbT.js";import{V as oe,a as I,b as _,c as O}from"./VExpansionPanels-B582iLUL.js";import{V as P}from"./VTable-Dq6iyDRH.js";import{V as N}from"./VSheet-DwEWmjya.js";import{b as R,c as ue}from"./VMain-5GZKef4L.js";import{f as g,e as l,b as a,u as v,x as m,k as o,c as b,N as U,F as D,q as A,j as p,g as f,l as me,p as he,T as pe,A as y,M as q}from"./tree-BneADi2F.js";import{F as fe}from"./Fields-d_R0orMD.js";import{V as C,a as w}from"./VRow-G7gk0dtN.js";import{V as ge}from"./VTextField-D3_NddNY.js";import{V as ye}from"./VSelect-B3p5P8x5.js";import{a as be,V as j,d as ce,e as k}from"./VTabs-C-Ik8Q_F.js";import{V as ve}from"./VForm-DKU-E0-n.js";import"./shared-C1gvLhAf.js";import"./VList-C1SRQdm1.js";import"./VDivider-BTvmUT3O.js";import"./autofocus-BzvwZNQb.js";import"./VInput-DgH-pXhD.js";import"./VCheckboxBtn-CL43jpUG.js";import"./VSelectionControl-C-Jhd07u.js";import"./VChip-CXzD2lkW.js";import"./VSlideGroup-Cz8sD7EC.js";const Ee=E`
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
`,Ve={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:S()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){const t=e.versionable_type.slice(e.versionable_type.lastIndexOf("\\")+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext("yes"):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext("no")}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:Ee,fetchPolicy:"no-cache",variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.element||{};this.element=Object.freeze({...s,bypages:Object.freeze((s.bypages||[]).map(i=>Object.freeze(i)))}),this.versions=Object.freeze((t.data?.element?.byversions||[]).map(i=>Object.freeze(this.mapVersion(i))).filter(i=>this.user.can(i.type.toLowerCase()+":view")))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function we(e,t,s,i,n,r){return p(),g(R,null,{default:l(()=>[a(N,{class:"scroll"},{default:l(()=>[a(oe,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=u=>e.panel=u),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(p(),g(I,{key:0},{default:l(()=>[a(_,null,{default:l(()=>[v(m(e.$gettext("Shared elements")),1)]),_:1}),a(O,null,{default:l(()=>[a(P,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,m(e.$gettext("ID")),1),o("th",null,m(e.$gettext("URL")),1),o("th",null,m(e.$gettext("Name")),1)])]),o("tbody",null,[(p(!0),b(D,null,U(e.element.bypages,u=>(p(),b("tr",{key:u.id},[o("td",null,m(u.id),1),o("td",null,m(u.path),1),o("td",null,m(u.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0),e.versions?.length?(p(),g(I,{key:1},{default:l(()=>[a(_,null,{default:l(()=>[...t[1]||(t[1]=[v("Versions",-1)])]),_:1}),a(O,null,{default:l(()=>[a(P,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,m(e.$gettext("ID")),1),o("th",null,m(e.$gettext("Type")),1),o("th",null,m(e.$gettext("Published")),1)])]),o("tbody",null,[(p(!0),b(D,null,U(e.versions,u=>(p(),b("tr",{key:u.id},[o("td",null,m(u.id),1),o("td",null,m(u.type),1),o("td",null,m(u.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const De=$(Ve,[["render",we],["__scopeId","data-v-c77920b4"]]),$e={components:{Fields:fe},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=H(),t=J(),s=L(),i=S();return{app:W(),user:i,languages:e,schemas:t,side:s,locales:K}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function Se(e,t,s,i,n,r){const u=f("Fields");return p(),g(R,null,{default:l(()=>[a(N,{class:"scroll"},{default:l(()=>[a(C,null,{default:l(()=>[a(w,{cols:"12",md:"6"},{default:l(()=>[a(ge,{ref:"name",readonly:r.readonly,modelValue:s.item.name,"onUpdate:modelValue":t[0]||(t[0]=h=>r.update("name",h)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),a(w,{cols:"12",md:"6"},{default:l(()=>[a(ye,{ref:"lang",items:i.locales(!0),readonly:r.readonly,modelValue:s.item.lang,"onUpdate:modelValue":t[1]||(t[1]=h=>r.update("lang",h)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),a(C,null,{default:l(()=>[a(w,{cols:"12"},{default:l(()=>[a(u,{ref:"field",data:s.item.data,"onUpdate:data":t[2]||(t[2]=h=>s.item.data=h),files:s.item.files,"onUpdate:files":t[3]||(t[3]=h=>s.item.files=h),fields:r.fields(s.item.type),readonly:r.readonly,assets:s.assets,type:s.item.type,onError:t[4]||(t[4]=h=>e.$emit("error",h)),onChange:t[5]||(t[5]=h=>e.$emit("update:item",s.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const Te=$($e,[["render",Se],["__scopeId","data-v-f67af8c0"]]),Ie=q(()=>c(()=>import("./ChangesDialog-D47zox3E.js"),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10]),import.meta.url)),_e=q(()=>c(()=>import("./HistoryDialog-DEMS1EAd.js"),__vite__mapDeps([11,1,2,3,4,5,6,7,8,9,12,13,14,15,16,17,18,19]),import.meta.url)),Oe=E`
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
`,Pe=E`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,Ue=E`
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
`,Ae={components:{AsideMeta:ae,ChangesDialog:Ie,DetailAppBar:ie,HistoryDialog:_e,ElementDetailRefs:De,ElementDetailItem:Te},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=X(),t=Y(),s=L(),i=S(),n=x(),r=ee();return{dirtyStore:e,side:s,user:i,messages:t,viewStack:n,changes:r}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can("element:view")){this.loading=!1;return}this.$apollo.query({query:Oe,fetchPolicy:"no-cache",variables:{id:this.item.id}}).then(e=>{if(this.destroyed)return;if(e.errors||!e.data.element)throw e;if(!e.data.element.latest)throw new Error("No version data available");const t=[],s={},i=e.data.element;this.reset(),Object.assign(this.item,T(i.latest?.data)),this.item.published=i.latest?.published,this.item.editor=i.latest?.editor,this.item.updated_at=i.latest?.created_at,this.latestId=i.latest?.id;for(const n of i.latest?.files||i.files||[])s[n.id]={...n,previews:V(n.previews)},t.push(n.id);this.assets=y(s),this.item.files=t,G(this,"element",this.item.id,n=>{!this.dirty&&this.user.can("element:view")&&(this.latestId=n.latest_id,Object.assign(this.item,te(n.data)))}),this.loading=!1}).catch(e=>{this.loading=!1,this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=y({}),this.destroyed=!0,this.changed=null,Z(this)},computed:{changeTargets(){return y({data:this.item})},hasConflict(){return de(this.changed)},historyCurrent(){const e=this.item,t=new Set(e.files||[]),s={};for(const i in this.assets)t.has(i)&&(s[i]=this.assets[i]);return y({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:y(s)})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},files(e){const t={};for(const s of e)t[s.id]={...s,previews:V(s.previews)};return t},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){ne(this,"element",{success:this.$gettext("Element published successfully"),scheduled:t=>this.$gettext("Element scheduled for publishing at %{date}",{date:t.toLocaleDateString()}),error:this.$gettext("Error publishing element")},e)},published(){this.publish(re(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:Pe,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.saveElement,i=s?.changed?y(T(s.changed)):null;(i?.latest?.id||s?.latest?.id)&&(this.latestId=i?.latest?.id??s.latest.id),le(this,i,this.$gettext("Element saved successfully"),e);const n=s?.latest;return this.item.published=n?.published??!1,this.item.publish_at=n?.publish_at??null,this.item.editor=n?.editor??this.item.editor,this.item.updated_at=n?.created_at??this.item.updated_at,this.item.latestId=this.latestId,this.changes.notify("element",this.item),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return Q(e)},writeText(e,t=[],s=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),c(async()=>{const{write:i}=await import("./ai-BDifcPYQ.js");return{write:i}},__vite__mapDeps([20,3,4,1,2,5,21]),import.meta.url).then(({write:i})=>i(e,t,s))},use(e){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0},translateText(e,t,s=null){return c(async()=>{const{translate:i}=await import("./ai-BDifcPYQ.js");return{translate:i}},__vite__mapDeps([20,3,4,1,2,5,21]),import.meta.url).then(({translate:i})=>i(e,t,s||this.item.lang))},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:Ue,fetchPolicy:"no-cache",variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(s=>Object.freeze({...s,data:V(s.data),files:Object.freeze(this.files(s.files||[]))}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Ce(e,t,s,i,n,r){const u=f("DetailAppBar"),h=f("ElementDetailItem"),F=f("ElementDetailRefs"),M=f("AsideMeta"),z=f("HistoryDialog"),B=f("ChangesDialog");return p(),b(D,null,[a(u,{type:"element",label:e.$gettext("Element"),name:s.item.name,stacked:s.stacked,dirty:e.dirty,error:e.error,conflict:r.hasConflict,changed:e.changed,published:s.item.published,"has-latest":!!e.latestId,saving:e.saving,publishing:e.publishing,"publish-at":e.publishAt,"onUpdate:publishAt":t[0]||(t[0]=d=>e.publishAt=d),"publish-time":e.publishTime,"onUpdate:publishTime":t[1]||(t[1]=d=>e.publishTime=d),onSave:t[2]||(t[2]=d=>r.save()),onPublish:t[3]||(t[3]=d=>r.publish()),onSchedule:r.published,onHistory:t[4]||(t[4]=d=>e.vhistory=!0),onChanges:t[5]||(t[5]=d=>e.vchanged=!0)},null,8,["label","name","stacked","dirty","error","conflict","changed","published","has-latest","saving","publishing","publish-at","publish-time","onSchedule"]),a(ue,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[e.loading?(p(),g(se,{key:0,indeterminate:"",color:"primary"})):(p(),g(ve,{key:1,onSubmit:t[8]||(t[8]=he(()=>{},["prevent"]))},{default:l(()=>[a(be,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[6]||(t[6]=d=>e.tab=d)},{default:l(()=>[a(j,{value:"element",class:me({changed:e.dirty,error:e.error})},{default:l(()=>[v(m(e.$gettext("Element")),1)]),_:1},8,["class"]),a(j,{value:"refs"},{default:l(()=>[v(m(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),a(ce,{modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=d=>e.tab=d),touch:!1},{default:l(()=>[a(k,{value:"element"},{default:l(()=>[a(h,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:s.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),a(k,{value:"refs"},{default:l(()=>[a(F,{item:s.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1}))]),_:1},8,["aria-label"]),a(M,{item:s.item},null,8,["item"]),(p(),g(pe,{to:"body"},[a(z,{modelValue:e.vhistory,"onUpdate:modelValue":t[9]||(t[9]=d=>e.vhistory=d),readonly:!i.user.can("element:save"),current:r.historyCurrent,load:r.loadVersions,onRevert:r.revertVersion,onApply:r.apply,onUse:t[10]||(t[10]=d=>r.use(d))},null,8,["modelValue","readonly","current","load","onRevert","onApply"]),a(B,{modelValue:e.vchanged,"onUpdate:modelValue":t[11]||(t[11]=d=>e.vchanged=d),changed:e.changed,targets:r.changeTargets,onResolve:t[12]||(t[12]=d=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const it=$(Ae,[["render",Ce]]);export{it as default};
