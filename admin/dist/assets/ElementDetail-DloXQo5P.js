const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-DzbY71S1.js","./index-Dj2h6P6L.js","./tree-BnNcOl7f.js","./graphql-C9B-AmXv.js","./graphql-upload-BJJQ6Vjw.js","./index-DNLIwF04.css","./utils-GbLosTlN.js","./VChip-DlHJ935E.js","./VSlideGroup-CnO0QMGH.js","./VSlideGroup-Dco48V-a.css","./VChip-BfxGbTdl.css","./ChangesDialog-C1PZAJRP.css","./HistoryDialog-DxmgpU40.js","./VCheckbox-Cw9nnHVf.js","./VCheckboxBtn-CiDRwsuv.js","./VSelectionControl-DUDfMm84.js","./VInput-Chtp_XEw.js","./VInput-DOMR_3Gs.css","./VSelectionControl-eTkllCWw.css","./VCheckbox-DXrFno_w.css","./HistoryDialog-BvHXOeUv.css","./ai-2eHSCcsp.js","./audio-CvQuggF0.js"])))=>i.map(i=>d[i]);
import{_ as $,G as w,s as B,K as H,c5 as _,v as J,n as v,c6 as W,N as G,H as K}from"./index-Dj2h6P6L.js";import{d as E}from"./graphql-C9B-AmXv.js";import{d as N,D as Q,A as X,i as Y,p as Z,j as x,k as ee,a as te,e as se,g as S,l as ie,m as T}from"./VMain-BFd3py53.js";import{a as q}from"./VSheet-CjN3gwap.js";import{c as ae,V as I,a as O,b as A}from"./VExpansionPanels-wefe0-ia.js";import{V as U}from"./VTable-C4Twv_JP.js";import{c as b,p as l,b as p,l as a,B as c,C as m,q as o,k as y,F as D,R as P,A as C,j as f,m as g,S as L,T as le,z as re,x as ne}from"./tree-BnNcOl7f.js";import{F as oe}from"./Fields-DqtEdDw7.js";import{g as de,f as j,i as me}from"./utils-GbLosTlN.js";import{a as R,V}from"./VRow-Ddq5s0bD.js";import{V as ue}from"./VTextField-DjrKf5qa.js";import{V as he}from"./VSelect-CLY7I_Dt.js";import{cleanEcho as pe,setupEcho as fe}from"./echo-D_7_zEtF.js";import{V as ge}from"./VForm-DJQ7_YOV.js";import"./graphql-upload-BJJQ6Vjw.js";import"./VList-DJD9af_D.js";import"./VDivider-h-0pFhud.js";import"./VDatePicker-BvcySuP9.js";import"./VPicker-DI1iEZBs.js";import"./VSlideGroup-CnO0QMGH.js";import"./autofocus-rnv_gcmj.js";import"./VInput-Chtp_XEw.js";import"./VCheckboxBtn-CiDRwsuv.js";import"./VSelectionControl-DUDfMm84.js";import"./VChip-DlHJ935E.js";const ye=E`
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
`,be={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:w()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){const t=e.versionable_type.slice(e.versionable_type.lastIndexOf("\\")+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext("yes"):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext("no")}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:ye,fetchPolicy:"no-cache",variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.element||{};this.element=Object.freeze({...s,bypages:Object.freeze((s.bypages||[]).map(i=>Object.freeze(i)))}),this.versions=Object.freeze((t.data?.element?.byversions||[]).map(i=>Object.freeze(this.mapVersion(i))).filter(i=>this.user.can(i.type.toLowerCase()+":view")))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function ve(e,t,s,i,u,r){return p(),b(N,null,{default:l(()=>[a(q,{class:"scroll"},{default:l(()=>[a(ae,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=d=>e.panel=d),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(p(),b(I,{key:0},{default:l(()=>[a(O,null,{default:l(()=>[c(m(e.$gettext("Shared elements")),1)]),_:1}),a(A,null,{default:l(()=>[a(U,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,m(e.$gettext("ID")),1),o("th",null,m(e.$gettext("URL")),1),o("th",null,m(e.$gettext("Name")),1)])]),o("tbody",null,[(p(!0),y(D,null,P(e.element.bypages,d=>(p(),y("tr",{key:d.id},[o("td",null,m(d.id),1),o("td",null,m(d.path),1),o("td",null,m(d.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):C("",!0),e.versions?.length?(p(),b(I,{key:1},{default:l(()=>[a(O,null,{default:l(()=>[...t[1]||(t[1]=[c("Versions",-1)])]),_:1}),a(A,null,{default:l(()=>[a(U,{density:"comfortable",hover:""},{default:l(()=>[o("thead",null,[o("tr",null,[o("th",null,m(e.$gettext("ID")),1),o("th",null,m(e.$gettext("Type")),1),o("th",null,m(e.$gettext("Published")),1)])]),o("tbody",null,[(p(!0),y(D,null,P(e.versions,d=>(p(),y("tr",{key:d.id},[o("td",null,m(d.id),1),o("td",null,m(d.type),1),o("td",null,m(d.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):C("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const ce=$(be,[["render",ve],["__scopeId","data-v-c77920b4"]]),Ee={components:{Fields:oe},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=B(),t=H(),s=_(),i=w();return{app:J(),user:i,languages:e,schemas:t,side:s,locales:de}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function Ve(e,t,s,i,u,r){const d=f("Fields");return p(),b(N,null,{default:l(()=>[a(q,{class:"scroll"},{default:l(()=>[a(R,null,{default:l(()=>[a(V,{cols:"12",md:"6"},{default:l(()=>[a(ue,{ref:"name",readonly:r.readonly,modelValue:s.item.name,"onUpdate:modelValue":t[0]||(t[0]=h=>r.update("name",h)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),a(V,{cols:"12",md:"6"},{default:l(()=>[a(he,{ref:"lang",items:i.locales(!0),readonly:r.readonly,modelValue:s.item.lang,"onUpdate:modelValue":t[1]||(t[1]=h=>r.update("lang",h)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),a(R,null,{default:l(()=>[a(V,{cols:"12"},{default:l(()=>[a(d,{ref:"field",data:s.item.data,"onUpdate:data":t[2]||(t[2]=h=>s.item.data=h),files:s.item.files,"onUpdate:files":t[3]||(t[3]=h=>s.item.files=h),fields:r.fields(s.item.type),readonly:r.readonly,assets:s.assets,type:s.item.type,onError:t[4]||(t[4]=h=>e.$emit("error",h)),onChange:t[5]||(t[5]=h=>e.$emit("update:item",s.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const De=$(Ee,[["render",Ve],["__scopeId","data-v-f67af8c0"]]),$e=L(()=>v(()=>import("./ChangesDialog-DzbY71S1.js"),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10,11]),import.meta.url)),we=L(()=>v(()=>import("./HistoryDialog-DxmgpU40.js"),__vite__mapDeps([12,1,2,3,4,5,6,7,8,9,10,13,14,15,16,17,18,19,20]),import.meta.url)),Se=E`
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
`,Te=E`
  mutation ($id: ID!, $input: ElementInput!, $files: [ID!], $latestId: ID) {
    saveElement(id: $id, input: $input, files: $files, latestId: $latestId) {
      id
      latest { id }
      changed
    }
  }
`,Ie=E`
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
`,Oe={components:{AsideMeta:X,ChangesDialog:$e,DetailAppBar:Q,HistoryDialog:we,ElementDetailRefs:ce,ElementDetailItem:De},props:{item:{type:Object,required:!0}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=W(),t=G(),s=K(),i=_(),u=w();return{dirtyStore:e,side:i,user:u,messages:s,viewStack:t}},created(){this.dirtyStore.register(()=>this.save(!0)),!(!this.item?.id||!this.user.can("element:view"))&&this.$apollo.query({query:Se,fetchPolicy:"no-cache",variables:{id:this.item.id}}).then(e=>{if(e.errors||!e.data.element)throw e;const t=[],s={},i=e.data.element;this.reset(),this.latestId=i.latest?.id;for(const u of i.latest?.files||i.files||[])s[u.id]=Object.freeze({...u,previews:j(u.previews)}),t.push(u.id);this.assets=g(s),this.item.files=t,fe(this,"element",this.item.id,u=>{!this.dirty&&this.user.can("element:view")&&u.editor!==this.user.me?.email&&(this.latestId=u.versionId,Object.assign(this.item,u.data))})}).catch(e=>{this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=g({}),this.destroyed=!0,this.changed=null,pe(this)},computed:{changeTargets(){return g({data:this.item})},hasConflict(){return ee(this.changed)},historyCurrent(){const e=this.item;return g({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:e.files})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){x(this,"element",{success:this.$gettext("Element published successfully"),scheduled:t=>this.$gettext("Element scheduled for publishing at %{date}",{date:t.toLocaleDateString()}),error:this.$gettext("Error publishing element")},e)},published(){this.publish(Z(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:Te,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:[...new Set(this.item.files)],latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.saveElement,i=s?.changed?g(JSON.parse(s.changed)):null;return(i?.latest?.id||s?.latest?.id)&&(this.latestId=i?.latest?.id??s.latest.id),Y(this,i,this.$gettext("Element saved successfully"),e),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return me(e)},writeText(e,t=[],s=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),v(async()=>{const{write:i}=await import("./ai-2eHSCcsp.js");return{write:i}},__vite__mapDeps([21,3,4,1,2,5,22,6]),import.meta.url).then(({write:i})=>i(e,t,s))},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.dirty=!0},translateText(e,t,s=null){return v(async()=>{const{translate:i}=await import("./ai-2eHSCcsp.js");return{translate:i}},__vite__mapDeps([21,3,4,1,2,5,22,6]),import.meta.url).then(({translate:i})=>i(e,t,s||this.item.lang))},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:Ie,fetchPolicy:"no-cache",variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(s=>Object.freeze({...s,data:j(s.data),files:Object.freeze(s.files.map(i=>i.id))}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Ae(e,t,s,i,u,r){const d=f("DetailAppBar"),h=f("ElementDetailItem"),k=f("ElementDetailRefs"),z=f("AsideMeta"),F=f("HistoryDialog"),M=f("ChangesDialog");return p(),y(D,null,[a(d,{type:"element",label:e.$gettext("Element"),name:s.item.name,dirty:e.dirty,error:e.error,conflict:r.hasConflict,changed:e.changed,published:s.item.published,"has-latest":!!e.latestId,saving:e.saving,publishing:e.publishing,"publish-at":e.publishAt,"onUpdate:publishAt":t[0]||(t[0]=n=>e.publishAt=n),"publish-time":e.publishTime,"onUpdate:publishTime":t[1]||(t[1]=n=>e.publishTime=n),onSave:t[2]||(t[2]=n=>r.save()),onPublish:t[3]||(t[3]=n=>r.publish()),onSchedule:r.published,onHistory:t[4]||(t[4]=n=>e.vhistory=!0),onChanges:t[5]||(t[5]=n=>e.vchanged=!0)},null,8,["label","name","dirty","error","conflict","changed","published","has-latest","saving","publishing","publish-at","publish-time","onSchedule"]),a(te,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[a(ge,{onSubmit:t[8]||(t[8]=re(()=>{},["prevent"]))},{default:l(()=>[a(se,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[6]||(t[6]=n=>e.tab=n)},{default:l(()=>[a(S,{value:"element",class:ne({changed:e.dirty,error:e.error})},{default:l(()=>[c(m(e.$gettext("Element")),1)]),_:1},8,["class"]),a(S,{value:"refs"},{default:l(()=>[c(m(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),a(ie,{modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=n=>e.tab=n),touch:!1},{default:l(()=>[a(T,{value:"element"},{default:l(()=>[a(h,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:s.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),a(T,{value:"refs"},{default:l(()=>[a(k,{item:s.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1})]),_:1},8,["aria-label"]),a(z,{item:s.item},null,8,["item"]),(p(),b(le,{to:"body"},[a(F,{modelValue:e.vhistory,"onUpdate:modelValue":t[9]||(t[9]=n=>e.vhistory=n),readonly:!i.user.can("element:save"),current:r.historyCurrent,load:r.loadVersions,onRevert:r.revertVersion,onApply:r.apply,onUse:t[10]||(t[10]=n=>r.use(n))},null,8,["modelValue","readonly","current","load","onRevert","onApply"]),a(M,{modelValue:e.vchanged,"onUpdate:modelValue":t[11]||(t[11]=n=>e.vchanged=n),changed:e.changed,targets:r.changeTargets,onResolve:t[12]||(t[12]=n=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const tt=$(Oe,[["render",Ae]]);export{tt as default};
