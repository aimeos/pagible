const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-NA5lDn5Q.js","./index-vFujDlHb.js","./ckeditor-D9GR95Rm.js","./graphql-JRtEtyn0.js","./shared-C1gvLhAf.js","./markdown-JOCB_Zgw.js","./index-BOZFZ_Sn.css","./VChip-DIvYdmIM.js","./VSlideGroup-BQdEDKUx.js","./VSlideGroup-Dco48V-a.css","./VChip-BfxGbTdl.css","./ChangesDialog-C1PZAJRP.css","./HistoryDialog-B3qvZmDA.js","./VCheckbox-ctRNlwam.js","./VCheckboxBtn-tmBi_iaW.js","./VSelectionControl-B3NHmfYR.js","./VInput-BZLCG9Fk.js","./VInput-DOMR_3Gs.css","./VSelectionControl-kMTQclwp.css","./VCheckbox-DXrFno_w.css","./HistoryDialog-B8k5lONJ.css","./ai-CiAqpYLk.js","./audio-CvQuggF0.js"])))=>i.map(i=>d[i]);
import{_ as $,G as I,c8 as H,M as J,cj as R,S as W,ck as G,Q as E,r as c,W as Q,w as S,cl as K,H as X,R as Y,aL as Z,cm as x,V as ee}from"./index-vFujDlHb.js";import{a as V}from"./graphql-JRtEtyn0.js";import{D as te,A as se,a as ie,p as ae,b as le,h as re}from"./publish-BGwJJlwu.js";import{V as ne,a as T,b as _,c as O}from"./VExpansionPanels-D-L_ykTC.js";import{V as P}from"./VTable-DiC6lSBB.js";import{V as L}from"./VSheet-DQX95jCo.js";import{b as N,c as oe}from"./VMain-BbmZggCM.js";import{m as g,w as l,a,s as v,t as u,l as d,c as b,b as U,F as D,p as A,o as p,r as f,q as de,D as me,T as ue,I as y,a8 as q}from"./ckeditor-D9GR95Rm.js";import{F as he}from"./Fields-DA_mIYVm.js";import{V as C,a as w}from"./VRow-GbQYGIXd.js";import{V as pe}from"./VTextField-D9ZdQD5C.js";import{V as fe}from"./VSelect-Cy1znvij.js";import{cleanEcho as ge,setupEcho as ye}from"./echo-KPQKeXWu.js";import{a as be,V as k,d as ce,e as j}from"./VTabs-BlZCrK9m.js";import{V as ve}from"./VForm-DWYek2J7.js";import"./shared-C1gvLhAf.js";import"./VList-DGuKolDA.js";import"./VDivider-BAaXeLRM.js";import"./VDatePicker-TYw0hK5b.js";import"./VPicker-B2UBUu4G.js";import"./markdown-JOCB_Zgw.js";import"./autofocus-DiVXsOfi.js";import"./VInput-BZLCG9Fk.js";import"./VCheckboxBtn-tmBi_iaW.js";import"./VSelectionControl-B3NHmfYR.js";import"./VChip-DIvYdmIM.js";import"./VSlideGroup-BQdEDKUx.js";const Ve=V`
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
`,Ee={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:I()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){const t=e.versionable_type.slice(e.versionable_type.lastIndexOf("\\")+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext("yes"):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext("no")}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:Ve,fetchPolicy:"no-cache",variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.element||{};this.element=Object.freeze({...s,bypages:Object.freeze((s.bypages||[]).map(i=>Object.freeze(i)))}),this.versions=Object.freeze((t.data?.element?.byversions||[]).map(i=>Object.freeze(this.mapVersion(i))).filter(i=>this.user.can(i.type.toLowerCase()+":view")))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function we(e,t,s,i,n,r){return p(),g(N,null,{default:l(()=>[a(L,{class:"scroll"},{default:l(()=>[a(ne,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=m=>e.panel=m),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(p(),g(T,{key:0},{default:l(()=>[a(_,null,{default:l(()=>[v(u(e.$gettext("Shared elements")),1)]),_:1}),a(O,null,{default:l(()=>[a(P,{density:"comfortable",hover:""},{default:l(()=>[d("thead",null,[d("tr",null,[d("th",null,u(e.$gettext("ID")),1),d("th",null,u(e.$gettext("URL")),1),d("th",null,u(e.$gettext("Name")),1)])]),d("tbody",null,[(p(!0),b(D,null,U(e.element.bypages,m=>(p(),b("tr",{key:m.id},[d("td",null,u(m.id),1),d("td",null,u(m.path),1),d("td",null,u(m.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0),e.versions?.length?(p(),g(T,{key:1},{default:l(()=>[a(_,null,{default:l(()=>[...t[1]||(t[1]=[v("Versions",-1)])]),_:1}),a(O,null,{default:l(()=>[a(P,{density:"comfortable",hover:""},{default:l(()=>[d("thead",null,[d("tr",null,[d("th",null,u(e.$gettext("ID")),1),d("th",null,u(e.$gettext("Type")),1),d("th",null,u(e.$gettext("Published")),1)])]),d("tbody",null,[(p(!0),b(D,null,U(e.versions,m=>(p(),b("tr",{key:m.id},[d("td",null,u(m.id),1),d("td",null,u(m.type),1),d("td",null,u(m.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const De=$(Ee,[["render",we],["__scopeId","data-v-c77920b4"]]),$e={components:{Fields:he},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=H(),t=J(),s=R(),i=I();return{app:W(),user:i,languages:e,schemas:t,side:s,locales:G}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function Ie(e,t,s,i,n,r){const m=f("Fields");return p(),g(N,null,{default:l(()=>[a(L,{class:"scroll"},{default:l(()=>[a(C,null,{default:l(()=>[a(w,{cols:"12",md:"6"},{default:l(()=>[a(pe,{ref:"name",readonly:r.readonly,modelValue:s.item.name,"onUpdate:modelValue":t[0]||(t[0]=h=>r.update("name",h)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),a(w,{cols:"12",md:"6"},{default:l(()=>[a(fe,{ref:"lang",items:i.locales(!0),readonly:r.readonly,modelValue:s.item.lang,"onUpdate:modelValue":t[1]||(t[1]=h=>r.update("lang",h)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),a(C,null,{default:l(()=>[a(w,{cols:"12"},{default:l(()=>[a(m,{ref:"field",data:s.item.data,"onUpdate:data":t[2]||(t[2]=h=>s.item.data=h),files:s.item.files,"onUpdate:files":t[3]||(t[3]=h=>s.item.files=h),fields:r.fields(s.item.type),readonly:r.readonly,assets:s.assets,type:s.item.type,onError:t[4]||(t[4]=h=>e.$emit("error",h)),onChange:t[5]||(t[5]=h=>e.$emit("update:item",s.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const Se=$($e,[["render",Ie],["__scopeId","data-v-f67af8c0"]]),Te=q(()=>c(()=>import("./ChangesDialog-NA5lDn5Q.js"),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10,11]),import.meta.url)),_e=q(()=>c(()=>import("./HistoryDialog-B3qvZmDA.js"),__vite__mapDeps([12,1,2,3,4,5,6,7,8,9,10,13,14,15,16,17,18,19,20]),import.meta.url)),Oe=V`
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
`,Pe=V`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,Ue=V`
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
`,Ae={components:{AsideMeta:se,ChangesDialog:Te,DetailAppBar:te,HistoryDialog:_e,ElementDetailRefs:De,ElementDetailItem:Se},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=K(),t=X(),s=R(),i=I(),n=Y(),r=Z();return{dirtyStore:e,side:s,user:i,messages:t,viewStack:n,changes:r}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can("element:view")){this.loading=!1;return}this.$apollo.query({query:Oe,fetchPolicy:"no-cache",variables:{id:this.item.id}}).then(e=>{if(this.destroyed)return;if(e.errors||!e.data.element)throw e;if(!e.data.element.latest)throw new Error("No version data available");const t=[],s={},i=e.data.element;this.reset(),Object.assign(this.item,S(i.latest?.data)),this.item.published=i.latest?.published,this.item.editor=i.latest?.editor,this.item.updated_at=i.latest?.created_at,this.latestId=i.latest?.id;for(const n of i.latest?.files||i.files||[])s[n.id]={...n,previews:E(n.previews)},t.push(n.id);this.assets=y(s),this.item.files=t,ye(this,"element",this.item.id,n=>{!this.dirty&&this.user.can("element:view")&&n.editor!==this.user.me?.email&&(this.latestId=n.versionId,Object.assign(this.item,x(n.data)))}),this.loading=!1}).catch(e=>{this.loading=!1,this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=y({}),this.destroyed=!0,this.changed=null,ge(this)},computed:{changeTargets(){return y({data:this.item})},hasConflict(){return re(this.changed)},historyCurrent(){const e=this.item,t=new Set(e.files||[]),s={};for(const i in this.assets)t.has(i)&&(s[i]=this.assets[i]);return y({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:y(s)})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},files(e){const t={};for(const s of e)t[s.id]={...s,previews:E(s.previews)};return t},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){le(this,"element",{success:this.$gettext("Element published successfully"),scheduled:t=>this.$gettext("Element scheduled for publishing at %{date}",{date:t.toLocaleDateString()}),error:this.$gettext("Error publishing element")},e)},published(){this.publish(ae(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:Pe,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.saveElement,i=s?.changed?y(S(s.changed)):null;(i?.latest?.id||s?.latest?.id)&&(this.latestId=i?.latest?.id??s.latest.id),ie(this,i,this.$gettext("Element saved successfully"),e);const n=s?.latest;return this.item.published=n?.published??!1,this.item.publish_at=n?.publish_at??null,this.item.editor=n?.editor??this.item.editor,this.item.updated_at=n?.created_at??this.item.updated_at,this.item.latestId=this.latestId,this.changes.notify("element",this.item),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return Q(e)},writeText(e,t=[],s=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),c(async()=>{const{write:i}=await import("./ai-CiAqpYLk.js");return{write:i}},__vite__mapDeps([21,3,4,1,2,5,6,22]),import.meta.url).then(({write:i})=>i(e,t,s))},use(e){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0},translateText(e,t,s=null){return c(async()=>{const{translate:i}=await import("./ai-CiAqpYLk.js");return{translate:i}},__vite__mapDeps([21,3,4,1,2,5,6,22]),import.meta.url).then(({translate:i})=>i(e,t,s||this.item.lang))},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:Ue,fetchPolicy:"no-cache",variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(s=>Object.freeze({...s,data:E(s.data),files:Object.freeze(this.files(s.files||[]))}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Ce(e,t,s,i,n,r){const m=f("DetailAppBar"),h=f("ElementDetailItem"),F=f("ElementDetailRefs"),z=f("AsideMeta"),M=f("HistoryDialog"),B=f("ChangesDialog");return p(),b(D,null,[a(m,{type:"element",label:e.$gettext("Element"),name:s.item.name,stacked:s.stacked,dirty:e.dirty,error:e.error,conflict:r.hasConflict,changed:e.changed,published:s.item.published,"has-latest":!!e.latestId,saving:e.saving,publishing:e.publishing,"publish-at":e.publishAt,"onUpdate:publishAt":t[0]||(t[0]=o=>e.publishAt=o),"publish-time":e.publishTime,"onUpdate:publishTime":t[1]||(t[1]=o=>e.publishTime=o),onSave:t[2]||(t[2]=o=>r.save()),onPublish:t[3]||(t[3]=o=>r.publish()),onSchedule:r.published,onHistory:t[4]||(t[4]=o=>e.vhistory=!0),onChanges:t[5]||(t[5]=o=>e.vchanged=!0)},null,8,["label","name","stacked","dirty","error","conflict","changed","published","has-latest","saving","publishing","publish-at","publish-time","onSchedule"]),a(oe,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[e.loading?(p(),g(ee,{key:0,indeterminate:"",color:"primary"})):(p(),g(ve,{key:1,onSubmit:t[8]||(t[8]=me(()=>{},["prevent"]))},{default:l(()=>[a(be,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[6]||(t[6]=o=>e.tab=o)},{default:l(()=>[a(k,{value:"element",class:de({changed:e.dirty,error:e.error})},{default:l(()=>[v(u(e.$gettext("Element")),1)]),_:1},8,["class"]),a(k,{value:"refs"},{default:l(()=>[v(u(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),a(ce,{modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=o=>e.tab=o),touch:!1},{default:l(()=>[a(j,{value:"element"},{default:l(()=>[a(h,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:s.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),a(j,{value:"refs"},{default:l(()=>[a(F,{item:s.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1}))]),_:1},8,["aria-label"]),a(z,{item:s.item},null,8,["item"]),(p(),g(ue,{to:"body"},[a(M,{modelValue:e.vhistory,"onUpdate:modelValue":t[9]||(t[9]=o=>e.vhistory=o),readonly:!i.user.can("element:save"),current:r.historyCurrent,load:r.loadVersions,onRevert:r.revertVersion,onApply:r.apply,onUse:t[10]||(t[10]=o=>r.use(o))},null,8,["modelValue","readonly","current","load","onRevert","onApply"]),a(B,{modelValue:e.vchanged,"onUpdate:modelValue":t[11]||(t[11]=o=>e.vchanged=o),changed:e.changed,targets:r.changeTargets,onResolve:t[12]||(t[12]=o=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const nt=$(Ae,[["render",Ce]]);export{nt as default};
