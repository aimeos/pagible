const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-D7cbX5fu.js","./index-VMqwOKvn.js","./tree-DsNO27hb.js","./graphql-DZQ80LTj.js","./index-B0nT-aLZ.css","./utils-BAjWA4iJ.js","./VChip-Db-p_Zuj.js","./VSlideGroup-DFzg5dBZ.js","./VSlideGroup-Dco48V-a.css","./VChip-BfxGbTdl.css","./ChangesDialog-C1PZAJRP.css","./HistoryDialog-DGg6mUFJ.js","./VCheckbox-DEiwF8zB.js","./VCheckboxBtn-BmY9RNT4.js","./VSelectionControl-CPboqDm3.js","./VInput-CicuzQo5.js","./VInput-DOMR_3Gs.css","./VSelectionControl-eTkllCWw.css","./VCheckbox-DXrFno_w.css","./HistoryDialog-DSFqYmVf.css","./ai-Scmg0rL0.js","./audio-CvQuggF0.js"])))=>i.map(i=>d[i]);
import{Q as $,dh as w,cW as B,da as H,dc as _,cF as J,O as c,cN as W,d2 as Q,dj as G,D as K}from"./index-VMqwOKvn.js";import{d as E}from"./graphql-DZQ80LTj.js";import{D as X,A as Y,a as Z,p as x,b as ee,h as te}from"./publish-V1XYi03W.js";import{b as k,c as se}from"./VMain-CvScK3ZU.js";import{V as R}from"./VSheet-CTlI3DyV.js";import{c as ae,V as S,b as I,a as T}from"./VExpansionPanels-B8JGcWpD.js";import{V as O}from"./VTable-Ck407tX3.js";import{j as g,al as l,W as p,n as i,m as v,a7 as m,i as d,l as b,F as D,$ as P,k as U,a1 as f,T as ie,D as y,o as L,ao as le,I as re}from"./tree-DsNO27hb.js";import{F as ne}from"./Fields-DaGaffCj.js";import{b as de,f as A,i as oe}from"./utils-BAjWA4iJ.js";import{a as C,V}from"./VRow-Dw3SJbA0.js";import{V as me}from"./VTextField-DdA5iGc4.js";import{V as ue}from"./VSelect-BNhtkebL.js";import{cleanEcho as he,setupEcho as pe}from"./echo-DxapthX0.js";import{V as fe}from"./VForm-Do4Kqtc4.js";import{a as ge,V as N,d as ye,e as j}from"./VTabs-Bk0Tzk8Z.js";import"./VList-C8fi6s8g.js";import"./VDivider-CHxjd7BY.js";import"./VDatePicker-1ZH5FyIv.js";import"./VPicker-D0u9OxZa.js";import"./autofocus-CCW12WuB.js";import"./VInput-CicuzQo5.js";import"./VCheckboxBtn-BmY9RNT4.js";import"./VSelectionControl-CPboqDm3.js";import"./VChip-Db-p_Zuj.js";import"./VSlideGroup-DFzg5dBZ.js";const be=E`
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
`,ce={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:w()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){const t=e.versionable_type.slice(e.versionable_type.lastIndexOf("\\")+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext("yes"):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext("no")}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:be,fetchPolicy:"no-cache",variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.element||{};this.element=Object.freeze({...s,bypages:Object.freeze((s.bypages||[]).map(a=>Object.freeze(a)))}),this.versions=Object.freeze((t.data?.element?.byversions||[]).map(a=>Object.freeze(this.mapVersion(a))).filter(a=>this.user.can(a.type.toLowerCase()+":view")))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function ve(e,t,s,a,u,r){return p(),g(k,null,{default:l(()=>[i(R,{class:"scroll"},{default:l(()=>[i(ae,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=o=>e.panel=o),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&a.user.can("page:view")?(p(),g(S,{key:0},{default:l(()=>[i(I,null,{default:l(()=>[v(m(e.$gettext("Shared elements")),1)]),_:1}),i(T,null,{default:l(()=>[i(O,{density:"comfortable",hover:""},{default:l(()=>[d("thead",null,[d("tr",null,[d("th",null,m(e.$gettext("ID")),1),d("th",null,m(e.$gettext("URL")),1),d("th",null,m(e.$gettext("Name")),1)])]),d("tbody",null,[(p(!0),b(D,null,P(e.element.bypages,o=>(p(),b("tr",{key:o.id},[d("td",null,m(o.id),1),d("td",null,m(o.path),1),d("td",null,m(o.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):U("",!0),e.versions?.length?(p(),g(S,{key:1},{default:l(()=>[i(I,null,{default:l(()=>[...t[1]||(t[1]=[v("Versions",-1)])]),_:1}),i(T,null,{default:l(()=>[i(O,{density:"comfortable",hover:""},{default:l(()=>[d("thead",null,[d("tr",null,[d("th",null,m(e.$gettext("ID")),1),d("th",null,m(e.$gettext("Type")),1),d("th",null,m(e.$gettext("Published")),1)])]),d("tbody",null,[(p(!0),b(D,null,P(e.versions,o=>(p(),b("tr",{key:o.id},[d("td",null,m(o.id),1),d("td",null,m(o.type),1),d("td",null,m(o.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):U("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const Ee=$(ce,[["render",ve],["__scopeId","data-v-c77920b4"]]),Ve={components:{Fields:ne},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=B(),t=H(),s=_(),a=w();return{app:J(),user:a,languages:e,schemas:t,side:s,locales:de}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function De(e,t,s,a,u,r){const o=f("Fields");return p(),g(k,null,{default:l(()=>[i(R,{class:"scroll"},{default:l(()=>[i(C,null,{default:l(()=>[i(V,{cols:"12",md:"6"},{default:l(()=>[i(me,{ref:"name",readonly:r.readonly,modelValue:s.item.name,"onUpdate:modelValue":t[0]||(t[0]=h=>r.update("name",h)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),i(V,{cols:"12",md:"6"},{default:l(()=>[i(ue,{ref:"lang",items:a.locales(!0),readonly:r.readonly,modelValue:s.item.lang,"onUpdate:modelValue":t[1]||(t[1]=h=>r.update("lang",h)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),i(C,null,{default:l(()=>[i(V,{cols:"12"},{default:l(()=>[i(o,{ref:"field",data:s.item.data,"onUpdate:data":t[2]||(t[2]=h=>s.item.data=h),files:s.item.files,"onUpdate:files":t[3]||(t[3]=h=>s.item.files=h),fields:r.fields(s.item.type),readonly:r.readonly,assets:s.assets,type:s.item.type,onError:t[4]||(t[4]=h=>e.$emit("error",h)),onChange:t[5]||(t[5]=h=>e.$emit("update:item",s.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const $e=$(Ve,[["render",De],["__scopeId","data-v-f67af8c0"]]),we=L(()=>c(()=>import("./ChangesDialog-D7cbX5fu.js"),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10]),import.meta.url)),Se=L(()=>c(()=>import("./HistoryDialog-DGg6mUFJ.js"),__vite__mapDeps([11,1,2,3,4,5,6,7,8,9,12,13,14,15,16,17,18,19]),import.meta.url)),Ie=E`
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
`,Oe=E`
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
`,Pe={components:{AsideMeta:Y,ChangesDialog:we,DetailAppBar:X,HistoryDialog:Se,ElementDetailRefs:Ee,ElementDetailItem:$e},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=W(),t=Q(),s=_(),a=w(),u=G();return{dirtyStore:e,side:s,user:a,messages:t,viewStack:u}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can("element:view")){this.loading=!1;return}this.$apollo.query({query:Ie,fetchPolicy:"no-cache",variables:{id:this.item.id}}).then(e=>{if(this.destroyed)return;if(e.errors||!e.data.element)throw e;if(!e.data.element.latest)throw new Error("No version data available");const t=[],s={},a=e.data.element;this.reset(),Object.assign(this.item,JSON.parse(a.latest?.data||"{}")),this.item.published=a.latest?.published,this.item.editor=a.latest?.editor,this.item.updated_at=a.latest?.created_at,this.latestId=a.latest?.id;for(const u of a.latest?.files||a.files||[])s[u.id]={...u,previews:A(u.previews)},t.push(u.id);this.assets=y(s),this.item.files=t,pe(this,"element",this.item.id,u=>{!this.dirty&&this.user.can("element:view")&&u.editor!==this.user.me?.email&&(this.latestId=u.versionId,Object.assign(this.item,u.data))}),this.loading=!1}).catch(e=>{this.loading=!1,this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=y({}),this.destroyed=!0,this.changed=null,he(this)},computed:{changeTargets(){return y({data:this.item})},hasConflict(){return te(this.changed)},historyCurrent(){const e=this.item;return y({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:e.files})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){ee(this,"element",{success:this.$gettext("Element published successfully"),scheduled:t=>this.$gettext("Element scheduled for publishing at %{date}",{date:t.toLocaleDateString()}),error:this.$gettext("Error publishing element")},e)},published(){this.publish(x(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:Te,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:[...new Set(this.item.files)],latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.saveElement,a=s?.changed?y(JSON.parse(s.changed)):null;return(a?.latest?.id||s?.latest?.id)&&(this.latestId=a?.latest?.id??s.latest.id),Z(this,a,this.$gettext("Element saved successfully"),e),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return oe(e)},writeText(e,t=[],s=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),c(async()=>{const{write:a}=await import("./ai-Scmg0rL0.js");return{write:a}},__vite__mapDeps([20,3,1,2,4,21,5]),import.meta.url).then(({write:a})=>a(e,t,s))},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.dirty=!0},translateText(e,t,s=null){return c(async()=>{const{translate:a}=await import("./ai-Scmg0rL0.js");return{translate:a}},__vite__mapDeps([20,3,1,2,4,21,5]),import.meta.url).then(({translate:a})=>a(e,t,s||this.item.lang))},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:Oe,fetchPolicy:"no-cache",variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(s=>Object.freeze({...s,data:A(s.data),files:Object.freeze(s.files.map(a=>a.id))}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Ue(e,t,s,a,u,r){const o=f("DetailAppBar"),h=f("ElementDetailItem"),q=f("ElementDetailRefs"),F=f("AsideMeta"),z=f("HistoryDialog"),M=f("ChangesDialog");return p(),b(D,null,[i(o,{type:"element",label:e.$gettext("Element"),name:s.item.name,stacked:s.stacked,dirty:e.dirty,error:e.error,conflict:r.hasConflict,changed:e.changed,published:s.item.published,"has-latest":!!e.latestId,saving:e.saving,publishing:e.publishing,"publish-at":e.publishAt,"onUpdate:publishAt":t[0]||(t[0]=n=>e.publishAt=n),"publish-time":e.publishTime,"onUpdate:publishTime":t[1]||(t[1]=n=>e.publishTime=n),onSave:t[2]||(t[2]=n=>r.save()),onPublish:t[3]||(t[3]=n=>r.publish()),onSchedule:r.published,onHistory:t[4]||(t[4]=n=>e.vhistory=!0),onChanges:t[5]||(t[5]=n=>e.vchanged=!0)},null,8,["label","name","stacked","dirty","error","conflict","changed","published","has-latest","saving","publishing","publish-at","publish-time","onSchedule"]),i(se,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[e.loading?(p(),g(K,{key:0,indeterminate:"",color:"primary"})):(p(),g(fe,{key:1,onSubmit:t[8]||(t[8]=le(()=>{},["prevent"]))},{default:l(()=>[i(ge,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[6]||(t[6]=n=>e.tab=n)},{default:l(()=>[i(N,{value:"element",class:re({changed:e.dirty,error:e.error})},{default:l(()=>[v(m(e.$gettext("Element")),1)]),_:1},8,["class"]),i(N,{value:"refs"},{default:l(()=>[v(m(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),i(ye,{modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=n=>e.tab=n),touch:!1},{default:l(()=>[i(j,{value:"element"},{default:l(()=>[i(h,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:s.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),i(j,{value:"refs"},{default:l(()=>[i(q,{item:s.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1}))]),_:1},8,["aria-label"]),i(F,{item:s.item},null,8,["item"]),(p(),g(ie,{to:"body"},[i(z,{modelValue:e.vhistory,"onUpdate:modelValue":t[9]||(t[9]=n=>e.vhistory=n),readonly:!a.user.can("element:save"),current:r.historyCurrent,load:r.loadVersions,onRevert:r.revertVersion,onApply:r.apply,onUse:t[10]||(t[10]=n=>r.use(n))},null,8,["modelValue","readonly","current","load","onRevert","onApply"]),i(M,{modelValue:e.vchanged,"onUpdate:modelValue":t[11]||(t[11]=n=>e.vchanged=n),changed:e.changed,targets:r.changeTargets,onResolve:t[12]||(t[12]=n=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const at=$(Pe,[["render",Ue]]);export{at as default};
