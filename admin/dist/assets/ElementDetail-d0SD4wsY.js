const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-DAyG5bWr.js","./index-JPhTVh7v.js","./tree-E0yOiz75.js","./graphql-DsaJiMYP.js","./index-BOZFZ_Sn.css","./utils-CmkUKknr.js","./VChip-BgaB7LUu.js","./VSlideGroup-BFuX3pMr.js","./VSlideGroup-Dco48V-a.css","./VChip-BfxGbTdl.css","./ChangesDialog-C1PZAJRP.css","./HistoryDialog-qrGx35Ta.js","./VCheckbox-DKwserHZ.js","./VCheckboxBtn-CczT4OdM.js","./VSelectionControl-GvVSmlzg.js","./VInput-D28zWkoX.js","./VInput-DOMR_3Gs.css","./VSelectionControl-kMTQclwp.css","./VCheckbox-DXrFno_w.css","./HistoryDialog-Bb_4ggUl.css","./ai-DGviQYwR.js","./audio-CvQuggF0.js"])))=>i.map(i=>d[i]);
import{_ as $,H as S,v as B,L as H,c3 as j,x as J,q as c,c4 as W,J as Q,O as G,aC as K,V as X}from"./index-JPhTVh7v.js";import{a as V}from"./graphql-DsaJiMYP.js";import{D as Y,A as Z,a as x,p as ee,b as te,h as se}from"./publish-BATXlPD6.js";import{c as N,a as ie}from"./VMain-CnpkuPos.js";import{V as R}from"./VSheet-yJEq-jJu.js";import{c as ae,V as I,a as T,b as O}from"./VExpansionPanels-lDPepchZ.js";import{V as _}from"./VTable-DTIaC5yT.js";import{c as g,p as l,b as p,l as a,B as v,C as u,q as o,k as b,F as D,Q as A,A as C,j as f,T as le,m as y,R as L,z as re,x as ne}from"./tree-E0yOiz75.js";import{F as de}from"./Fields-DhGCfOWf.js";import{g as oe,f as E,i as me}from"./utils-CmkUKknr.js";import{a as P,V as w}from"./VRow-BhD7BF59.js";import{V as ue}from"./VTextField-eHDPVbfL.js";import{V as he}from"./VSelect-BH7sRI87.js";import{cleanEcho as pe,setupEcho as fe}from"./echo-B5x6ETJ3.js";import{V as ge}from"./VForm-BLUtFXoE.js";import{V as ye,b as U,d as be,e as k}from"./VTabs-DcWYGArT.js";import"./VList-CVTC9x0Q.js";import"./VDivider-B-_pzqla.js";import"./VDatePicker-C2jmXWc3.js";import"./VPicker-CQvE8jfU.js";import"./autofocus-DVWQZLZK.js";import"./VInput-D28zWkoX.js";import"./VCheckboxBtn-CczT4OdM.js";import"./VSelectionControl-GvVSmlzg.js";import"./VChip-BgaB7LUu.js";import"./VSlideGroup-BFuX3pMr.js";const ce=V`
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
`,ve={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:S()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){const t=e.versionable_type.slice(e.versionable_type.lastIndexOf("\\")+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext("yes"):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext("no")}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:ce,fetchPolicy:"no-cache",variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.element||{};this.element=Object.freeze({...s,bypages:Object.freeze((s.bypages||[]).map(i=>Object.freeze(i)))}),this.versions=Object.freeze((t.data?.element?.byversions||[]).map(i=>Object.freeze(this.mapVersion(i))).filter(i=>this.user.can(i.type.toLowerCase()+":view")))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function Ve(e,t,s,i,n,r){return p(),g(N,null,{default:l(()=>[a(R,{class:"scroll"},{default:l(()=>[a(ae,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=m=>e.panel=m),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(p(),g(I,{key:0},{default:l(()=>[a(T,null,{default:l(()=>[v(u(e.$gettext("Shared elements")),1)]),_:1}),a(O,null,{default:l(()=>[a(_,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,u(e.$gettext("ID")),1),o("th",null,u(e.$gettext("URL")),1),o("th",null,u(e.$gettext("Name")),1)])]),o("tbody",null,[(p(!0),b(D,null,A(e.element.bypages,m=>(p(),b("tr",{key:m.id},[o("td",null,u(m.id),1),o("td",null,u(m.path),1),o("td",null,u(m.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):C("",!0),e.versions?.length?(p(),g(I,{key:1},{default:l(()=>[a(T,null,{default:l(()=>[...t[1]||(t[1]=[v("Versions",-1)])]),_:1}),a(O,null,{default:l(()=>[a(_,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,u(e.$gettext("ID")),1),o("th",null,u(e.$gettext("Type")),1),o("th",null,u(e.$gettext("Published")),1)])]),o("tbody",null,[(p(!0),b(D,null,A(e.versions,m=>(p(),b("tr",{key:m.id},[o("td",null,u(m.id),1),o("td",null,u(m.type),1),o("td",null,u(m.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):C("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const Ee=$(ve,[["render",Ve],["__scopeId","data-v-c77920b4"]]),we={components:{Fields:de},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=B(),t=H(),s=j(),i=S();return{app:J(),user:i,languages:e,schemas:t,side:s,locales:oe}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function De(e,t,s,i,n,r){const m=f("Fields");return p(),g(N,null,{default:l(()=>[a(R,{class:"scroll"},{default:l(()=>[a(P,null,{default:l(()=>[a(w,{cols:"12",md:"6"},{default:l(()=>[a(ue,{ref:"name",readonly:r.readonly,modelValue:s.item.name,"onUpdate:modelValue":t[0]||(t[0]=h=>r.update("name",h)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),a(w,{cols:"12",md:"6"},{default:l(()=>[a(he,{ref:"lang",items:i.locales(!0),readonly:r.readonly,modelValue:s.item.lang,"onUpdate:modelValue":t[1]||(t[1]=h=>r.update("lang",h)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),a(P,null,{default:l(()=>[a(w,{cols:"12"},{default:l(()=>[a(m,{ref:"field",data:s.item.data,"onUpdate:data":t[2]||(t[2]=h=>s.item.data=h),files:s.item.files,"onUpdate:files":t[3]||(t[3]=h=>s.item.files=h),fields:r.fields(s.item.type),readonly:r.readonly,assets:s.assets,type:s.item.type,onError:t[4]||(t[4]=h=>e.$emit("error",h)),onChange:t[5]||(t[5]=h=>e.$emit("update:item",s.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const $e=$(we,[["render",De],["__scopeId","data-v-f67af8c0"]]),Se=L(()=>c(()=>import("./ChangesDialog-DAyG5bWr.js"),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10]),import.meta.url)),Ie=L(()=>c(()=>import("./HistoryDialog-qrGx35Ta.js"),__vite__mapDeps([11,1,2,3,4,5,6,7,8,9,12,13,14,15,16,17,18,19]),import.meta.url)),Te=V`
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
`,Oe=V`
  mutation ($id: ID!, $input: ElementInput!, $files: [ID!], $latestId: ID) {
    saveElement(id: $id, input: $input, files: $files, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,_e=V`
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
`,Ae={components:{AsideMeta:Z,ChangesDialog:Se,DetailAppBar:Y,HistoryDialog:Ie,ElementDetailRefs:Ee,ElementDetailItem:$e},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=W(),t=Q(),s=j(),i=S(),n=G(),r=K();return{dirtyStore:e,side:s,user:i,messages:t,viewStack:n,changes:r}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can("element:view")){this.loading=!1;return}this.$apollo.query({query:Te,fetchPolicy:"no-cache",variables:{id:this.item.id}}).then(e=>{if(this.destroyed)return;if(e.errors||!e.data.element)throw e;if(!e.data.element.latest)throw new Error("No version data available");const t=[],s={},i=e.data.element;this.reset(),Object.assign(this.item,JSON.parse(i.latest?.data||"{}")),this.item.published=i.latest?.published,this.item.editor=i.latest?.editor,this.item.updated_at=i.latest?.created_at,this.latestId=i.latest?.id;for(const n of i.latest?.files||i.files||[])s[n.id]={...n,previews:E(n.previews)},t.push(n.id);this.assets=y(s),this.item.files=t,fe(this,"element",this.item.id,n=>{!this.dirty&&this.user.can("element:view")&&n.editor!==this.user.me?.email&&(this.latestId=n.versionId,Object.assign(this.item,n.data))}),this.loading=!1}).catch(e=>{this.loading=!1,this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=y({}),this.destroyed=!0,this.changed=null,pe(this)},computed:{changeTargets(){return y({data:this.item})},hasConflict(){return se(this.changed)},historyCurrent(){const e=this.item,t=new Set(e.files||[]),s={};for(const i in this.assets)t.has(i)&&(s[i]=this.assets[i]);return y({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:y(s)})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},files(e){const t={};for(const s of e)t[s.id]={...s,previews:E(s.previews)};return t},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){te(this,"element",{success:this.$gettext("Element published successfully"),scheduled:t=>this.$gettext("Element scheduled for publishing at %{date}",{date:t.toLocaleDateString()}),error:this.$gettext("Error publishing element")},e)},published(){this.publish(ee(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:Oe,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:[...new Set(this.item.files)],latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.saveElement,i=s?.changed?y(JSON.parse(s.changed)):null;(i?.latest?.id||s?.latest?.id)&&(this.latestId=i?.latest?.id??s.latest.id),x(this,i,this.$gettext("Element saved successfully"),e);const n=s?.latest;return this.item.published=n?.published??!1,this.item.publish_at=n?.publish_at??null,this.item.editor=n?.editor??this.item.editor,this.item.updated_at=n?.created_at??this.item.updated_at,this.item.latestId=this.latestId,this.changes.notify("element",this.item),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return me(e)},writeText(e,t=[],s=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),c(async()=>{const{write:i}=await import("./ai-DGviQYwR.js");return{write:i}},__vite__mapDeps([20,3,1,2,4,21,5]),import.meta.url).then(({write:i})=>i(e,t,s))},use(e){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0},translateText(e,t,s=null){return c(async()=>{const{translate:i}=await import("./ai-DGviQYwR.js");return{translate:i}},__vite__mapDeps([20,3,1,2,4,21,5]),import.meta.url).then(({translate:i})=>i(e,t,s||this.item.lang))},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:_e,fetchPolicy:"no-cache",variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(s=>Object.freeze({...s,data:E(s.data),files:Object.freeze(this.files(s.files||[]))}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Ce(e,t,s,i,n,r){const m=f("DetailAppBar"),h=f("ElementDetailItem"),q=f("ElementDetailRefs"),F=f("AsideMeta"),z=f("HistoryDialog"),M=f("ChangesDialog");return p(),b(D,null,[a(m,{type:"element",label:e.$gettext("Element"),name:s.item.name,stacked:s.stacked,dirty:e.dirty,error:e.error,conflict:r.hasConflict,changed:e.changed,published:s.item.published,"has-latest":!!e.latestId,saving:e.saving,publishing:e.publishing,"publish-at":e.publishAt,"onUpdate:publishAt":t[0]||(t[0]=d=>e.publishAt=d),"publish-time":e.publishTime,"onUpdate:publishTime":t[1]||(t[1]=d=>e.publishTime=d),onSave:t[2]||(t[2]=d=>r.save()),onPublish:t[3]||(t[3]=d=>r.publish()),onSchedule:r.published,onHistory:t[4]||(t[4]=d=>e.vhistory=!0),onChanges:t[5]||(t[5]=d=>e.vchanged=!0)},null,8,["label","name","stacked","dirty","error","conflict","changed","published","has-latest","saving","publishing","publish-at","publish-time","onSchedule"]),a(ie,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[e.loading?(p(),g(X,{key:0,indeterminate:"",color:"primary"})):(p(),g(ge,{key:1,onSubmit:t[8]||(t[8]=re(()=>{},["prevent"]))},{default:l(()=>[a(ye,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[6]||(t[6]=d=>e.tab=d)},{default:l(()=>[a(U,{value:"element",class:ne({changed:e.dirty,error:e.error})},{default:l(()=>[v(u(e.$gettext("Element")),1)]),_:1},8,["class"]),a(U,{value:"refs"},{default:l(()=>[v(u(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),a(be,{modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=d=>e.tab=d),touch:!1},{default:l(()=>[a(k,{value:"element"},{default:l(()=>[a(h,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:s.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),a(k,{value:"refs"},{default:l(()=>[a(q,{item:s.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1}))]),_:1},8,["aria-label"]),a(F,{item:s.item},null,8,["item"]),(p(),g(le,{to:"body"},[a(z,{modelValue:e.vhistory,"onUpdate:modelValue":t[9]||(t[9]=d=>e.vhistory=d),readonly:!i.user.can("element:save"),current:r.historyCurrent,load:r.loadVersions,onRevert:r.revertVersion,onApply:r.apply,onUse:t[10]||(t[10]=d=>r.use(d))},null,8,["modelValue","readonly","current","load","onRevert","onApply"]),a(M,{modelValue:e.vchanged,"onUpdate:modelValue":t[11]||(t[11]=d=>e.vchanged=d),changed:e.changed,targets:r.changeTargets,onResolve:t[12]||(t[12]=d=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const at=$(Ae,[["render",Ce]]);export{at as default};
