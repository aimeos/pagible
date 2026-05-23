const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-Os62AYBZ.js","./index-Cbw9jNdF.js","./tree-BEgJ7c65.js","./graphql-CvgRNBEg.js","./graphql-upload-6RPwClA1.js","./index-Dn5S4da4.css","./utils-Cz-JGKp4.js","./VChip-CUEi8Zmk.js","./VSlideGroup-BqUhOh4F.js","./VSlideGroup-Dco48V-a.css","./VChip-BfxGbTdl.css","./ChangesDialog-C1PZAJRP.css","./HistoryDialog-BIJwM7d7.js","./VCheckbox-DRraQ5nK.js","./VCheckboxBtn-CGj22RbB.js","./VSelectionControl-Day0D25N.js","./VInput-B2zEporP.js","./VInput-DOMR_3Gs.css","./VSelectionControl-eTkllCWw.css","./VCheckbox-DXrFno_w.css","./HistoryDialog-CHM6FI48.css","./ai-B4OqGw9W.js","./audio-CvQuggF0.js"])))=>i.map(i=>d[i]);
import{Q as $,dg as w,cV as B,d9 as H,db as N,cE as J,O as c,cM as W,d1 as Q}from"./index-Cbw9jNdF.js";import{f as E}from"./graphql-CvgRNBEg.js";import{D as G,A as K,a as X,p as Y,b as Z,h as x}from"./publish-sl148X0L.js";import{b as R,c as ee}from"./VMain-kfbGaEaE.js";import{V as L}from"./VSheet-C-IrZa5P.js";import{c as te,V as I,b as T,a as S}from"./VExpansionPanels-BruelNAf.js";import{V as O}from"./VTable-ca77Kls4.js";import{j as y,ak as l,V as p,n as a,m as v,a6 as m,i as d,l as b,F as D,_ as U,k as A,a0 as f,T as se,D as g,o as k,an as ie,I as ae}from"./tree-BEgJ7c65.js";import{F as le}from"./Fields-CjwvurGS.js";import{b as re,f as P,i as ne}from"./utils-Cz-JGKp4.js";import{a as C,V}from"./VRow-C6oQI7Cr.js";import{V as de}from"./VTextField-DSXQCuyN.js";import{V as oe}from"./VSelect-BWTwbs5P.js";import{cleanEcho as me,setupEcho as ue}from"./echo-C2aq1jZy.js";import{V as he}from"./VForm-5AfWwMAH.js";import{a as pe,V as _,d as fe,e as j}from"./VTabs-8p4rruN2.js";import"./graphql-upload-6RPwClA1.js";import"./VList-DLsAQE9m.js";import"./VDivider-CKLYkPRa.js";import"./VDatePicker-DKl0O01v.js";import"./VPicker-C5BXQ_sg.js";import"./autofocus-C38906_s.js";import"./VInput-B2zEporP.js";import"./VCheckboxBtn-CGj22RbB.js";import"./VSelectionControl-Day0D25N.js";import"./VChip-CUEi8Zmk.js";import"./VSlideGroup-BqUhOh4F.js";const ge=E`
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
`,be={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:w()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){const t=e.versionable_type.slice(e.versionable_type.lastIndexOf("\\")+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext("yes"):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext("no")}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can("element:view")||this.$apollo.query({query:ge,fetchPolicy:"no-cache",variables:{id:e.id}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.element||{};this.element=Object.freeze({...s,bypages:Object.freeze((s.bypages||[]).map(i=>Object.freeze(i)))}),this.versions=Object.freeze((t.data?.element?.byversions||[]).map(i=>Object.freeze(this.mapVersion(i))).filter(i=>this.user.can(i.type.toLowerCase()+":view")))}).catch(t=>{this.$log("ElementDetailRef::watch(item): Error fetching element",e,t)})}}}};function ye(e,t,s,i,u,r){return p(),y(R,null,{default:l(()=>[a(L,{class:"scroll"},{default:l(()=>[a(te,{modelValue:e.panel,"onUpdate:modelValue":t[0]||(t[0]=o=>e.panel=o),elevation:"0",multiple:""},{default:l(()=>[e.element.bypages?.length&&i.user.can("page:view")?(p(),y(I,{key:0},{default:l(()=>[a(T,null,{default:l(()=>[v(m(e.$gettext("Shared elements")),1)]),_:1}),a(S,null,{default:l(()=>[a(O,{density:"comfortable",hover:""},{default:l(()=>[d("thead",null,[d("tr",null,[d("th",null,m(e.$gettext("ID")),1),d("th",null,m(e.$gettext("URL")),1),d("th",null,m(e.$gettext("Name")),1)])]),d("tbody",null,[(p(!0),b(D,null,U(e.element.bypages,o=>(p(),b("tr",{key:o.id},[d("td",null,m(o.id),1),d("td",null,m(o.path),1),d("td",null,m(o.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0),e.versions?.length?(p(),y(I,{key:1},{default:l(()=>[a(T,null,{default:l(()=>[...t[1]||(t[1]=[v("Versions",-1)])]),_:1}),a(S,null,{default:l(()=>[a(O,{density:"comfortable",hover:""},{default:l(()=>[d("thead",null,[d("tr",null,[d("th",null,m(e.$gettext("ID")),1),d("th",null,m(e.$gettext("Type")),1),d("th",null,m(e.$gettext("Published")),1)])]),d("tbody",null,[(p(!0),b(D,null,U(e.versions,o=>(p(),b("tr",{key:o.id},[d("td",null,m(o.id),1),d("td",null,m(o.type),1),d("td",null,m(o.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):A("",!0)]),_:1},8,["modelValue"])]),_:1})]),_:1})}const ce=$(be,[["render",ye],["__scopeId","data-v-c77920b4"]]),ve={components:{Fields:le},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:["update:item","error"],setup(){const e=B(),t=H(),s=N(),i=w();return{app:J(),user:i,languages:e,schemas:t,side:s,locales:re}},computed:{readonly(){return!this.user.can("element:save")}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit("update:item",this.item)}}};function Ee(e,t,s,i,u,r){const o=f("Fields");return p(),y(R,null,{default:l(()=>[a(L,{class:"scroll"},{default:l(()=>[a(C,null,{default:l(()=>[a(V,{cols:"12",md:"6"},{default:l(()=>[a(de,{ref:"name",readonly:r.readonly,modelValue:s.item.name,"onUpdate:modelValue":t[0]||(t[0]=h=>r.update("name",h)),variant:"underlined",label:e.$gettext("Name"),counter:"255",maxlength:"255"},null,8,["readonly","modelValue","label"])]),_:1}),a(V,{cols:"12",md:"6"},{default:l(()=>[a(oe,{ref:"lang",items:i.locales(!0),readonly:r.readonly,modelValue:s.item.lang,"onUpdate:modelValue":t[1]||(t[1]=h=>r.update("lang",h)),variant:"underlined",label:e.$gettext("Language")},null,8,["items","readonly","modelValue","label"])]),_:1})]),_:1}),a(C,null,{default:l(()=>[a(V,{cols:"12"},{default:l(()=>[a(o,{ref:"field",data:s.item.data,"onUpdate:data":t[2]||(t[2]=h=>s.item.data=h),files:s.item.files,"onUpdate:files":t[3]||(t[3]=h=>s.item.files=h),fields:r.fields(s.item.type),readonly:r.readonly,assets:s.assets,type:s.item.type,onError:t[4]||(t[4]=h=>e.$emit("error",h)),onChange:t[5]||(t[5]=h=>e.$emit("update:item",s.item))},null,8,["data","files","fields","readonly","assets","type"])]),_:1})]),_:1})]),_:1})]),_:1})}const Ve=$(ve,[["render",Ee],["__scopeId","data-v-f67af8c0"]]),De=k(()=>c(()=>import("./ChangesDialog-Os62AYBZ.js"),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10,11]),import.meta.url)),$e=k(()=>c(()=>import("./HistoryDialog-BIJwM7d7.js"),__vite__mapDeps([12,1,2,3,4,5,6,7,8,9,10,13,14,15,16,17,18,19,20]),import.meta.url)),we=E`
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
`,Ie=E`
  mutation ($id: ID!, $input: ElementInput!, $files: [ID!], $latestId: ID) {
    saveElement(id: $id, input: $input, files: $files, latestId: $latestId) {
      id
      latest { id }
      changed
    }
  }
`,Te=E`
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
`,Se={components:{AsideMeta:K,ChangesDialog:De,DetailAppBar:G,HistoryDialog:$e,ElementDetailRefs:ce,ElementDetailItem:Ve},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:"element"}),setup(){const e=W(),t=Q(),s=N(),i=w();return{dirtyStore:e,side:s,user:i,messages:t}},created(){this.dirtyStore.register(()=>this.save(!0)),!(!this.item?.id||!this.user.can("element:view"))&&this.$apollo.query({query:we,fetchPolicy:"no-cache",variables:{id:this.item.id}}).then(e=>{if(e.errors||!e.data.element)throw e;const t=[],s={},i=e.data.element;this.reset(),Object.assign(this.item,JSON.parse(i.latest?.data||"{}")),this.item.published=i.latest?.published,this.item.editor=i.latest?.editor,this.item.updated_at=i.latest?.created_at,this.latestId=i.latest?.id;for(const u of i.latest?.files||i.files||[])s[u.id]=Object.freeze({...u,previews:P(u.previews)}),t.push(u.id);this.assets=g(s),this.item.files=t,ue(this,"element",this.item.id,u=>{!this.dirty&&this.user.can("element:view")&&u.editor!==this.user.me?.email&&(this.latestId=u.versionId,Object.assign(this.item,u.data))})}).catch(e=>{this.messages.add(this.$gettext("Error fetching element")+`:
`+e,"error"),this.$log("ElementDetail::watch(item): Error fetching element",e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=g({}),this.destroyed=!0,this.changed=null,me(this)},computed:{changeTargets(){return g({data:this.item})},hasConflict(){return x(this.changed)},historyCurrent(){const e=this.item;return g({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:e.files})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},itemUpdated(){this.$emit("update:item",this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){Z(this,"element",{success:this.$gettext("Element published successfully"),scheduled:t=>this.$gettext("Element scheduled for publishing at %{date}",{date:t.toLocaleDateString()}),error:this.$gettext("Error publishing element")},e)},published(){this.publish(Y(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can("element:save")?this.error?(this.messages.add(this.$gettext("There are invalid fields, please resolve the errors first"),"error"),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:Ie,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},files:[...new Set(this.item.files)],latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;const s=t.data?.saveElement,i=s?.changed?g(JSON.parse(s.changed)):null;return(i?.latest?.id||s?.latest?.id)&&(this.latestId=i?.latest?.id??s.latest.id),X(this,i,this.$gettext("Element saved successfully"),e),!0}).catch(t=>{this.messages.add(this.$gettext("Error saving element")+`:
`+t,"error"),this.$log("ElementDetail::save(): Error saving element",t)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve(!1))},title(e){return ne(e)},writeText(e,t=[],s=[]){return Array.isArray(t)||(t=[t]),t.push("element data as JSON: "+JSON.stringify(this.item.data)),t.push("required output language: "+(this.item.lang||"en")),c(async()=>{const{write:i}=await import("./ai-B4OqGw9W.js");return{write:i}},__vite__mapDeps([21,3,4,1,2,5,22,6]),import.meta.url).then(({write:i})=>i(e,t,s))},use(e){Object.assign(this.item,e.data),this.vhistory=!1,this.dirty=!0},translateText(e,t,s=null){return c(async()=>{const{translate:i}=await import("./ai-B4OqGw9W.js");return{translate:i}},__vite__mapDeps([21,3,4,1,2,5,22,6]),import.meta.url).then(({translate:i})=>i(e,t,s||this.item.lang))},versions(e){return this.user.can("element:view")?e?this.$apollo.query({query:Te,fetchPolicy:"no-cache",variables:{id:e}}).then(t=>{if(t.errors||!t.data.element)throw t;return(t.data.element.versions||[]).map(s=>Object.freeze({...s,data:P(s.data),files:Object.freeze(s.files.map(i=>i.id))}))}).catch(t=>{this.messages.add(this.$gettext("Error fetching element versions")+`:
`+t,"error"),this.$log("ElementDetail::versions(): Error fetching element versions",e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext("Permission denied"),"error"),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Oe(e,t,s,i,u,r){const o=f("DetailAppBar"),h=f("ElementDetailItem"),q=f("ElementDetailRefs"),F=f("AsideMeta"),z=f("HistoryDialog"),M=f("ChangesDialog");return p(),b(D,null,[a(o,{type:"element",label:e.$gettext("Element"),name:s.item.name,stacked:s.stacked,dirty:e.dirty,error:e.error,conflict:r.hasConflict,changed:e.changed,published:s.item.published,"has-latest":!!e.latestId,saving:e.saving,publishing:e.publishing,"publish-at":e.publishAt,"onUpdate:publishAt":t[0]||(t[0]=n=>e.publishAt=n),"publish-time":e.publishTime,"onUpdate:publishTime":t[1]||(t[1]=n=>e.publishTime=n),onSave:t[2]||(t[2]=n=>r.save()),onPublish:t[3]||(t[3]=n=>r.publish()),onSchedule:r.published,onHistory:t[4]||(t[4]=n=>e.vhistory=!0),onChanges:t[5]||(t[5]=n=>e.vchanged=!0)},null,8,["label","name","stacked","dirty","error","conflict","changed","published","has-latest","saving","publishing","publish-at","publish-time","onSchedule"]),a(ee,{class:"element-details","aria-label":e.$gettext("Element")},{default:l(()=>[a(he,{onSubmit:t[8]||(t[8]=ie(()=>{},["prevent"]))},{default:l(()=>[a(pe,{"fixed-tabs":"",modelValue:e.tab,"onUpdate:modelValue":t[6]||(t[6]=n=>e.tab=n)},{default:l(()=>[a(_,{value:"element",class:ae({changed:e.dirty,error:e.error})},{default:l(()=>[v(m(e.$gettext("Element")),1)]),_:1},8,["class"]),a(_,{value:"refs"},{default:l(()=>[v(m(e.$gettext("Used by")),1)]),_:1})]),_:1},8,["modelValue"]),a(fe,{modelValue:e.tab,"onUpdate:modelValue":t[7]||(t[7]=n=>e.tab=n),touch:!1},{default:l(()=>[a(j,{value:"element"},{default:l(()=>[a(h,{"onUpdate:item":r.itemUpdated,onError:r.errorUpdated,assets:e.assets,item:s.item},null,8,["onUpdate:item","onError","assets","item"])]),_:1}),a(j,{value:"refs"},{default:l(()=>[a(q,{item:s.item},null,8,["item"])]),_:1})]),_:1},8,["modelValue"])]),_:1})]),_:1},8,["aria-label"]),a(F,{item:s.item},null,8,["item"]),(p(),y(se,{to:"body"},[a(z,{modelValue:e.vhistory,"onUpdate:modelValue":t[9]||(t[9]=n=>e.vhistory=n),readonly:!i.user.can("element:save"),current:r.historyCurrent,load:r.loadVersions,onRevert:r.revertVersion,onApply:r.apply,onUse:t[10]||(t[10]=n=>r.use(n))},null,8,["modelValue","readonly","current","load","onRevert","onApply"]),a(M,{modelValue:e.vchanged,"onUpdate:modelValue":t[11]||(t[11]=n=>e.vchanged=n),changed:e.changed,targets:r.changeTargets,onResolve:t[12]||(t[12]=n=>e.dirty=!0)},null,8,["modelValue","changed","targets"])]))],64)}const st=$(Se,[["render",Oe]]);export{st as default};
