const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-DBBZZkp_.js","./index-MY9yDipQ.js","./ckeditor-BsBCcW3u.js","./graphql-DLiK_bhK.js","./markdown-JOCB_Zgw.js","./index-BOZFZ_Sn.css","./VChip-CVScQMlB.js","./VSlideGroup-CZcZm8Fm.js","./VSlideGroup-Dco48V-a.css","./VChip-BfxGbTdl.css","./ChangesDialog-C1PZAJRP.css","./HistoryDialog-BAh212Md.js","./VCheckbox-DDi-j8Au.js","./VCheckboxBtn-BY7Uh0GH.js","./VSelectionControl-4lpc5GeH.js","./VInput-CalOsCZh.js","./VInput-DOMR_3Gs.css","./VSelectionControl-kMTQclwp.css","./VCheckbox-DXrFno_w.css","./HistoryDialog-B8k5lONJ.css","./ai-B6vJ2Uen.js","./audio-CvQuggF0.js"])))=>i.map(i=>d[i]);
import{_ as $,G as S,c8 as H,M as J,cj as R,S as W,ck as G,Q as V,r as c,W as Q,w as I,cl as K,H as X,R as Y,aL as Z,cm as x,V as ee}from"./index-MY9yDipQ.js";import{a as E}from"./graphql-DLiK_bhK.js";import{D as te,A as se,a as ie,p as ae,b as le,h as re}from"./publish-C3pIRZO3.js";import{V as ne,a as T,b as _,c as O}from"./VExpansionPanels-ljmbmL9Q.js";import{V as P}from"./VTable-Bdtlhqgl.js";import{V as L}from"./VSheet-DQzm-zVm.js";import{b as N,c as de}from"./VMain-Dlxa8BPg.js";import{p as g,w as l,a,u as v,t as u,m as o,c as b,b as U,F as D,q as A,o as p,r as f,s as oe,E as me,T as ue,J as y,a9 as q}from"./ckeditor-BsBCcW3u.js";import{F as he}from"./Fields-CWNW7z29.js";import{V as C,a as w}from"./VRow-D3A7u4zv.js";import{V as pe}from"./VTextField-ADvg0Prk.js";import{V as fe}from"./VSelect-O4ckhjZY.js";import{cleanEcho as ge,setupEcho as ye}from"./echo-DICUEacY.js";import{a as be,V as k,d as ce,e as j}from"./VTabs-Bj2auEd4.js";import{V as ve}from"./VForm-DVAo_RdJ.js";import"./VList-4iYir8_a.js";import"./VDivider-r_hJ2liy.js";import"./VDatePicker-Bys1m4Z5.js";import"./VPicker-Cs0XTPTQ.js";import"./markdown-JOCB_Zgw.js";import"./autofocus-BfYspyIY.js";import"./VInput-CalOsCZh.js";import"./VCheckboxBtn-BY7Uh0GH.js";import"./VSelectionControl-4lpc5GeH.js";import"./VChip-CVScQMlB.js";import"./VSlideGroup-CZcZm8Fm.js";const Ee=E`
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
`,Ve={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:S()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){const t=e.versionable_type.slice(e.versionable_type.lastIndexOf("\\")+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext("yes"):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext("no")}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:Ee,fetchPolicy:"no-cache",variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.element||{};this.element=Object.freeze({...s,bypages:Object.freeze((s.bypages||[]).map(i=>Object.freeze(i)))}),this.versions=Object.freeze((t.data?.element?.byversions||[]).map(i=>Object.freeze(this.mapVersion(i))).filter(i=>this.user.can(i.type.toLowerCase()+":view")))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function we(e,t,s,i,n,r){return p(),g(N,null,{default:l(()=>[a(L,{class:"scroll"},{default:l(()=>[a(ne,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=m=>e.panel=m),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(p(),g(T,{key:0},{default:l(()=>[a(_,null,{default:l(()=>[v(u(e.$gettext("Shared elements")),1)]),_:1}),a(O,null,{default:l(()=>[a(P,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,u(e.$gettext("ID")),1),o("th",null,u(e.$gettext("URL")),1),o("th",null,u(e.$gettext("Name")),1)])]),o("tbody",null,[(p(!0),b(D,null,U(e.element.bypages,m=>(p(),b("tr",{key:m.id},[o("td",null,u(m.id),1),o("td",null,u(m.path),1),o("td",null,u(m.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0),e.versions?.length?(p(),g(T,{key:1},{default:l(()=>[a(_,null,{default:l(()=>[...t[1]||(t[1]=[v("Versions",-1)])]),_:1}),a(O,null,{default:l(()=>[a(P,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,u(e.$gettext("ID")),1),o("th",null,u(e.$gettext("Type")),1),o("th",null,u(e.$gettext("Published")),1)])]),o("tbody",null,[(p(!0),b(D,null,U(e.versions,m=>(p(),b("tr",{key:m.id},[o("td",null,u(m.id),1),o("td",null,u(m.type),1),o("td",null,u(m.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const De=$(Ve,[["render",we],["__scopeId","data-v-c77920b4"]]),$e={components:{Fields:he},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=H(),t=J(),s=R(),i=S();return{app:W(),user:i,languages:e,schemas:t,side:s,locales:G}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function Se(e,t,s,i,n,r){const m=f("Fields");return p(),g(N,null,{default:l(()=>[a(L,{class:"scroll"},{default:l(()=>[a(C,null,{default:l(()=>[a(w,{cols:"12",md:"6"},{default:l(()=>[a(pe,{ref:"name",readonly:r.readonly,modelValue:s.item.name,"onUpdate:modelValue":t[0]||(t[0]=h=>r.update("name",h)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),a(w,{cols:"12",md:"6"},{default:l(()=>[a(fe,{ref:"lang",items:i.locales(!0),readonly:r.readonly,modelValue:s.item.lang,"onUpdate:modelValue":t[1]||(t[1]=h=>r.update("lang",h)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),a(C,null,{default:l(()=>[a(w,{cols:"12"},{default:l(()=>[a(m,{ref:"field",data:s.item.data,"onUpdate:data":t[2]||(t[2]=h=>s.item.data=h),files:s.item.files,"onUpdate:files":t[3]||(t[3]=h=>s.item.files=h),fields:r.fields(s.item.type),readonly:r.readonly,assets:s.assets,type:s.item.type,onError:t[4]||(t[4]=h=>e.$emit("error",h)),onChange:t[5]||(t[5]=h=>e.$emit("update:item",s.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const Ie=$($e,[["render",Se],["__scopeId","data-v-f67af8c0"]]),Te=q(()=>c(()=>import("./ChangesDialog-DBBZZkp_.js"),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10]),import.meta.url)),_e=q(()=>c(()=>import("./HistoryDialog-BAh212Md.js"),__vite__mapDeps([11,1,2,3,4,5,6,7,8,9,12,13,14,15,16,17,18,19]),import.meta.url)),Oe=E`
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
`,Ae={components:{AsideMeta:se,ChangesDialog:Te,DetailAppBar:te,HistoryDialog:_e,ElementDetailRefs:De,ElementDetailItem:Ie},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=K(),t=X(),s=R(),i=S(),n=Y(),r=Z();return{dirtyStore:e,side:s,user:i,messages:t,viewStack:n,changes:r}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can("element:view")){this.loading=!1;return}this.$apollo.query({query:Oe,fetchPolicy:"no-cache",variables:{id:this.item.id}}).then(e=>{if(this.destroyed)return;if(e.errors||!e.data.element)throw e;if(!e.data.element.latest)throw new Error("No version data available");const t=[],s={},i=e.data.element;this.reset(),Object.assign(this.item,I(i.latest?.data)),this.item.published=i.latest?.published,this.item.editor=i.latest?.editor,this.item.updated_at=i.latest?.created_at,this.latestId=i.latest?.id;for(const n of i.latest?.files||i.files||[])s[n.id]={...n,previews:V(n.previews)},t.push(n.id);this.assets=y(s),this.item.files=t,ye(this,"element",this.item.id,n=>{!this.dirty&&this.user.can("element:view")&&n.editor!==this.user.me?.email&&(this.latestId=n.versionId,Object.assign(this.item,x(n.data)))}),this.loading=!1}).catch(e=>{this.loading=!1,this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=y({}),this.destroyed=!0,this.changed=null,ge(this)},computed:{changeTargets(){return y({data:this.item})},hasConflict(){return re(this.changed)},historyCurrent(){const e=this.item,t=new Set(e.files||[]),s={};for(const i in this.assets)t.has(i)&&(s[i]=this.assets[i]);return y({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:y(s)})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},files(e){const t={};for(const s of e)t[s.id]={...s,previews:V(s.previews)};return t},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){le(this,"element",{success:this.$gettext("Element published successfully"),scheduled:t=>this.$gettext("Element scheduled for publishing at %{date}",{date:t.toLocaleDateString()}),error:this.$gettext("Error publishing element")},e)},published(){this.publish(ae(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:Pe,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.saveElement,i=s?.changed?y(I(s.changed)):null;(i?.latest?.id||s?.latest?.id)&&(this.latestId=i?.latest?.id??s.latest.id),ie(this,i,this.$gettext("Element saved successfully"),e);const n=s?.latest;return this.item.published=n?.published??!1,this.item.publish_at=n?.publish_at??null,this.item.editor=n?.editor??this.item.editor,this.item.updated_at=n?.created_at??this.item.updated_at,this.item.latestId=this.latestId,this.changes.notify("element",this.item),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return Q(e)},writeText(e,t=[],s=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),c(async()=>{const{write:i}=await import("./ai-B6vJ2Uen.js");return{write:i}},__vite__mapDeps([20,3,2,4,1,5,21]),import.meta.url).then(({write:i})=>i(e,t,s))},use(e){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0},translateText(e,t,s=null){return c(async()=>{const{translate:i}=await import("./ai-B6vJ2Uen.js");return{translate:i}},__vite__mapDeps([20,3,2,4,1,5,21]),import.meta.url).then(({translate:i})=>i(e,t,s||this.item.lang))},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:Ue,fetchPolicy:"no-cache",variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(s=>Object.freeze({...s,data:V(s.data),files:Object.freeze(this.files(s.files||[]))}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Ce(e,t,s,i,n,r){const m=f("DetailAppBar"),h=f("ElementDetailItem"),F=f("ElementDetailRefs"),z=f("AsideMeta"),M=f("HistoryDialog"),B=f("ChangesDialog");return p(),b(D,null,[a(m,{type:"element",label:e.$gettext("Element"),name:s.item.name,stacked:s.stacked,dirty:e.dirty,error:e.error,conflict:r.hasConflict,changed:e.changed,published:s.item.published,"has-latest":!!e.latestId,saving:e.saving,publishing:e.publishing,"publish-at":e.publishAt,"onUpdate:publishAt":t[0]||(t[0]=d=>e.publishAt=d),"publish-time":e.publishTime,"onUpdate:publishTime":t[1]||(t[1]=d=>e.publishTime=d),onSave:t[2]||(t[2]=d=>r.save()),onPublish:t[3]||(t[3]=d=>r.publish()),onSchedule:r.published,onHistory:t[4]||(t[4]=d=>e.vhistory=!0),onChanges:t[5]||(t[5]=d=>e.vchanged=!0)},null,8,["label","name","stacked","dirty","error","conflict","changed","published","has-latest","saving","publishing","publish-at","publish-time","onSchedule"]),a(de,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[e.loading?(p(),g(ee,{key:0,indeterminate:"",color:"primary"})):(p(),g(ve,{key:1,onSubmit:t[8]||(t[8]=me(()=>{},["prevent"]))},{default:l(()=>[a(be,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[6]||(t[6]=d=>e.tab=d)},{default:l(()=>[a(k,{value:"element",class:oe({changed:e.dirty,error:e.error})},{default:l(()=>[v(u(e.$gettext("Element")),1)]),_:1},8,["class"]),a(k,{value:"refs"},{default:l(()=>[v(u(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),a(ce,{modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=d=>e.tab=d),touch:!1},{default:l(()=>[a(j,{value:"element"},{default:l(()=>[a(h,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:s.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),a(j,{value:"refs"},{default:l(()=>[a(F,{item:s.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1}))]),_:1},8,["aria-label"]),a(z,{item:s.item},null,8,["item"]),(p(),g(ue,{to:"body"},[a(M,{modelValue:e.vhistory,"onUpdate:modelValue":t[9]||(t[9]=d=>e.vhistory=d),readonly:!i.user.can("element:save"),current:r.historyCurrent,load:r.loadVersions,onRevert:r.revertVersion,onApply:r.apply,onUse:t[10]||(t[10]=d=>r.use(d))},null,8,["modelValue","readonly","current","load","onRevert","onApply"]),a(B,{modelValue:e.vchanged,"onUpdate:modelValue":t[11]||(t[11]=d=>e.vchanged=d),changed:e.changed,targets:r.changeTargets,onResolve:t[12]||(t[12]=d=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const rt=$(Ae,[["render",Ce]]);export{rt as default};
