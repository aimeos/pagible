const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-MHTJFotu.js","./charts-CjXvq1zh.js","./rolldown-runtime-QTnfLwEv.js","./_rolldown_dynamic_import_helper-BLwCltdF.js","./index-BHOBllp_.js","./dimensions-Cj53uB-V.js","./en-BH83F3-h.js","./graphql-CQpNdpSk.js","./VDefaultsProvider-BxbSI2CX.js","./transition-7S8Rs99o.js","./VOverlay-Cy8xgXtu.js","./deepEqual-DpGg6utq.js","./display-C-n9w4sK.js","./lazy-bf8Pv2R_.js","./router-D-Uogn79.js","./VOverlay-DG-PukqI.css","./VBadge-Cp9v_ewg.js","./loader-zrTQzX-0.js","./VIcon-BVdffwF0.js","./VIcon-DTxGzRyY.css","./rounded-DdTDohEf.js","./loader-BBmTwUdl.css","./VBadge-CZSms9Md.css","./forwardRefs-RiU8Uqxp.js","./position-DYrZg6Ta.js","./index-itsLrt_c.css","./VChip-BAr3sgDV.js","./VSlideGroup-CF-87aVz.js","./VSlideGroup-9_hX0lAw.css","./VChip-CMsQ_jbl.css","./ChangesDialog-QOprQ8JM.css","./HistoryDialog-CznF5TyJ.js","./VCheckbox-CSrrJKgO.js","./VInput-BTGw3iFT.js","./VInput-BsP73X2A.css","./VCheckboxBtn-KZm_X27H.js","./VSelectionControl-D9SW3Xsc.js","./VSelectionControl-DCyuVH7W.css","./VCheckbox-ChRKi1kj.css","./HistoryDialog-DmB4zt3g.css","./ai-Ddy55sUj.js","./audio-DAy57wYP.js"])))=>i.map(i=>d[i]);
import{B as e,Bt as t,H as n,Lt as r,W as i,b as a,c as o,g as s,h as c,lt as l,m as u,nt as d,o as f,p,v as m,y as h}from"./charts-CjXvq1zh.js";import{in as g}from"./dimensions-Cj53uB-V.js";import{n as _}from"./_rolldown_dynamic_import_helper-BLwCltdF.js";import{r as v}from"./graphql-CQpNdpSk.js";import{i as y}from"./loader-zrTQzX-0.js";import{a as b,t as ee}from"./VMain-Bd1pMURR.js";import{n as x,t as S}from"./VRow-D96ps0V2.js";import{An as C,Cn as w,Fn as T,In as E,Ln as D,Nn as O,Sn as te,Tn as ne,b as k,bn as re,gn as A,hn as j,vn as M,wn as N,xn as P}from"./index-BHOBllp_.js";import{a as F,i as I,n as L,r as R,s as z,t as B}from"./publish-Ds9OMUaT.js";import{t as V}from"./Fields-1e_l6DWJ.js";import{t as H}from"./VTextField-Cbop_yhl.js";import{a as U,o as W,r as G,t as K}from"./VTabs-DRArlG2h.js";import{t as q}from"./VSelect-DQ7wR-rO.js";import{t as J}from"./VSheet-W7IHwmdt.js";import{i as Y,n as X,r as Z,t as ie}from"./VExpansionPanels-DgpElZ_v.js";import{t as Q}from"./VTable-D1DV5AX_.js";import{r as ae,t as oe}from"./echo-7Pc0_6kw.js";import{t as se}from"./VForm-C0sDxoaO.js";var ce=v`
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
`,le={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:N()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){let t=e.versionable_type.slice(e.versionable_type.lastIndexOf(`\\`)+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext(`yes`):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext(`no`)}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can(`element:view`)||this.$apollo.query({query:ce,fetchPolicy:`no-cache`,variables:{id:e.id}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.element||{};this.element=Object.freeze({...t,bypages:Object.freeze((t.bypages||[]).map(e=>Object.freeze(e)))}),this.versions=Object.freeze((e.data?.element?.byversions||[]).map(e=>Object.freeze(this.mapVersion(e))).filter(e=>this.user.can(e.type.toLowerCase()+`:view`)))}).catch(t=>{this.$log(`ElementDetailRef::watch(item): Error fetching element`,e,t)})}}}};function ue(r,i,a,o,l,g){return e(),u(b,null,{default:d(()=>[h(J,{class:`scroll`},{default:d(()=>[h(ie,{modelValue:r.panel,"onUpdate:modelValue":i[0]||=e=>r.panel=e,elevation:`0`,multiple:``},{default:d(()=>[r.element.bypages?.length&&o.user.can(`page:view`)?(e(),u(X,{key:0},{default:d(()=>[h(Z,null,{default:d(()=>[m(t(r.$gettext(`Shared elements`)),1)]),_:1}),h(Y,null,{default:d(()=>[h(Q,{density:`comfortable`,hover:``},{default:d(()=>[p(`thead`,null,[p(`tr`,null,[p(`th`,null,t(r.$gettext(`ID`)),1),p(`th`,null,t(r.$gettext(`URL`)),1),p(`th`,null,t(r.$gettext(`Name`)),1)])]),p(`tbody`,null,[(e(!0),s(f,null,n(r.element.bypages,n=>(e(),s(`tr`,{key:n.id},[p(`td`,null,t(n.id),1),p(`td`,null,t(n.path),1),p(`td`,null,t(n.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):c(``,!0),r.versions?.length?(e(),u(X,{key:1},{default:d(()=>[h(Z,null,{default:d(()=>[...i[1]||=[m(`Versions`,-1)]]),_:1}),h(Y,null,{default:d(()=>[h(Q,{density:`comfortable`,hover:``},{default:d(()=>[p(`thead`,null,[p(`tr`,null,[p(`th`,null,t(r.$gettext(`ID`)),1),p(`th`,null,t(r.$gettext(`Type`)),1),p(`th`,null,t(r.$gettext(`Published`)),1)])]),p(`tbody`,null,[(e(!0),s(f,null,n(r.versions,n=>(e(),s(`tr`,{key:n.id},[p(`td`,null,t(n.id),1),p(`td`,null,t(n.type),1),p(`td`,null,t(n.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):c(``,!0)]),_:1},8,[`modelValue`])]),_:1})]),_:1})}var de=k(le,[[`render`,ue],[`__scopeId`,`data-v-c77920b4`]]),fe={components:{Fields:V},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:[`update:item`,`error`],setup(){let e=re(),t=te(),n=w(),r=N();return{app:j(),user:r,languages:e,schemas:t,side:n,locales:T}},computed:{readonly(){return!this.user.can(`element:save`)}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit(`update:item`,this.item)}}};function pe(t,n,r,a,o,s){let c=i(`Fields`);return e(),u(b,null,{default:d(()=>[h(J,{class:`scroll`},{default:d(()=>[h(S,null,{default:d(()=>[h(x,{cols:`12`,md:`6`},{default:d(()=>[h(H,{ref:`name`,readonly:s.readonly,modelValue:r.item.name,"onUpdate:modelValue":n[0]||=e=>s.update(`name`,e),variant:`underlined`,label:t.$gettext(`Name`),counter:`255`,maxlength:`255`},null,8,[`readonly`,`modelValue`,`label`])]),_:1}),h(x,{cols:`12`,md:`6`},{default:d(()=>[h(q,{ref:`lang`,items:a.locales(!0),readonly:s.readonly,modelValue:r.item.lang,"onUpdate:modelValue":n[1]||=e=>s.update(`lang`,e),variant:`underlined`,label:t.$gettext(`Language`)},null,8,[`items`,`readonly`,`modelValue`,`label`])]),_:1})]),_:1}),h(S,null,{default:d(()=>[h(x,{cols:`12`},{default:d(()=>[h(c,{ref:`field`,data:r.item.data,"onUpdate:data":n[2]||=e=>r.item.data=e,files:r.item.files,"onUpdate:files":n[3]||=e=>r.item.files=e,fields:s.fields(r.item.type),readonly:s.readonly,assets:r.assets,type:r.item.type,onError:n[4]||=e=>t.$emit(`error`,e),onChange:n[5]||=e=>t.$emit(`update:item`,r.item)},null,8,[`data`,`files`,`fields`,`readonly`,`assets`,`type`])]),_:1})]),_:1})]),_:1})]),_:1})}var $=k(fe,[[`render`,pe],[`__scopeId`,`data-v-f67af8c0`]]),me=a(()=>_(()=>import(`./ChangesDialog-MHTJFotu.js`),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30]),import.meta.url)),he=a(()=>_(()=>import(`./HistoryDialog-CznF5TyJ.js`),__vite__mapDeps([31,1,2,5,6,3,8,18,19,20,4,7,9,10,11,12,13,14,15,16,17,21,22,23,24,25,26,27,28,29,32,33,34,35,36,37,38,39]),import.meta.url)),ge=v`
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
`,_e=v`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,ve=v`
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
`,ye={components:{AsideMeta:I,ChangesDialog:me,DetailAppBar:R,HistoryDialog:he,ElementDetailRefs:de,ElementDetailItem:$},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:`element`}),setup(){let e=M(),t=P();return{dirtyStore:e,side:w(),user:N(),messages:t,viewStack:ne(),changes:A()}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can(`element:view`)){this.loading=!1;return}this.$apollo.query({query:ge,fetchPolicy:`no-cache`,variables:{id:this.item.id}}).then(e=>{if(this.destroyed)return;if(e.errors||!e.data.element)throw e;if(!e.data.element.latest)throw Error(`No version data available`);let t=[],n={},r=e.data.element;this.reset(),Object.assign(this.item,E(r.latest?.data)),this.item.published=r.latest?.published,this.item.editor=r.latest?.editor,this.item.updated_at=r.latest?.created_at,this.latestId=r.latest?.id;for(let e of r.latest?.files||r.files||[])n[e.id]={...e,previews:C(e.previews)},t.push(e.id);this.assets=l(n),this.item.files=t,ae(this,`element`,this.item.id,e=>{!this.dirty&&this.user.can(`element:view`)&&e.editor!==this.user.me?.email&&(this.latestId=e.versionId,Object.assign(this.item,D(e.data)))}),this.loading=!1}).catch(e=>{this.loading=!1,this.messages.add(this.$gettext(`Error fetching element`)+`:
`+e,`error`),this.$log(`ElementDetail::watch(item): Error fetching element`,e)})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=l({}),this.destroyed=!0,this.changed=null,oe(this)},computed:{changeTargets(){return l({data:this.item})},hasConflict(){return z(this.changed)},historyCurrent(){let e=this.item,t=new Set(e.files||[]),n={};for(let e in this.assets)t.has(e)&&(n[e]=this.assets[e]);return l({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:l(n)})}},methods:{apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},files(e){let t={};for(let n of e)t[n.id]={...n,previews:C(n.previews)};return t},itemUpdated(){this.$emit(`update:item`,this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){L(this,`element`,{success:this.$gettext(`Element published successfully`),scheduled:e=>this.$gettext(`Element scheduled for publishing at %{date}`,{date:e.toLocaleDateString()}),error:this.$gettext(`Error publishing element`)},e)},published(){this.publish(B(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can(`element:save`)?this.error?(this.messages.add(this.$gettext(`There are invalid fields, please resolve the errors first`),`error`),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:_e,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;let n=t.data?.saveElement,r=n?.changed?l(E(n.changed)):null;(r?.latest?.id||n?.latest?.id)&&(this.latestId=r?.latest?.id??n.latest.id),F(this,r,this.$gettext(`Element saved successfully`),e);let i=n?.latest;return this.item.published=i?.published??!1,this.item.publish_at=i?.publish_at??null,this.item.editor=i?.editor??this.item.editor,this.item.updated_at=i?.created_at??this.item.updated_at,this.item.latestId=this.latestId,this.changes.notify(`element`,this.item),!0}).catch(e=>{this.messages.add(this.$gettext(`Error saving element`)+`:
`+e,`error`),this.$log(`ElementDetail::save(): Error saving element`,e)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve(!1))},title(e){return O(e)},writeText(e,t=[],n=[]){return Array.isArray(t)||(t=[t]),t.push(`element data as JSON: `+JSON.stringify(this.item.data)),t.push(`required output language: `+(this.item.lang||`en`)),_(async()=>{let{write:e}=await import(`./ai-Ddy55sUj.js`);return{write:e}},__vite__mapDeps([40,7,2,4,1,5,6,3,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,41]),import.meta.url).then(({write:r})=>r(e,t,n))},use(e){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0},translateText(e,t,n=null){return _(async()=>{let{translate:e}=await import(`./ai-Ddy55sUj.js`);return{translate:e}},__vite__mapDeps([40,7,2,4,1,5,6,3,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,41]),import.meta.url).then(({translate:r})=>r(e,t,n||this.item.lang))},versions(e){return this.user.can(`element:view`)?e?this.$apollo.query({query:ve,fetchPolicy:`no-cache`,variables:{id:e}}).then(e=>{if(e.errors||!e.data.element)throw e;return(e.data.element.versions||[]).map(e=>Object.freeze({...e,data:C(e.data),files:Object.freeze(this.files(e.files||[]))}))}).catch(t=>{this.messages.add(this.$gettext(`Error fetching element versions`)+`:
`+t,`error`),this.$log(`ElementDetail::versions(): Error fetching element versions`,e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function be(n,a,c,l,p,_){let v=i(`DetailAppBar`),b=i(`ElementDetailItem`),x=i(`ElementDetailRefs`),S=i(`AsideMeta`),C=i(`HistoryDialog`),w=i(`ChangesDialog`);return e(),s(f,null,[h(v,{type:`element`,label:n.$gettext(`Element`),name:c.item.name,stacked:c.stacked,dirty:n.dirty,error:n.error,conflict:_.hasConflict,changed:n.changed,published:c.item.published,"has-latest":!!n.latestId,saving:n.saving,publishing:n.publishing,"publish-at":n.publishAt,"onUpdate:publishAt":a[0]||=e=>n.publishAt=e,"publish-time":n.publishTime,"onUpdate:publishTime":a[1]||=e=>n.publishTime=e,onSave:a[2]||=e=>_.save(),onPublish:a[3]||=e=>_.publish(),onSchedule:_.published,onHistory:a[4]||=e=>n.vhistory=!0,onChanges:a[5]||=e=>n.vchanged=!0},null,8,[`label`,`name`,`stacked`,`dirty`,`error`,`conflict`,`changed`,`published`,`has-latest`,`saving`,`publishing`,`publish-at`,`publish-time`,`onSchedule`]),h(ee,{class:`element-details`,"aria-label":n.$gettext(`Element`)},{default:d(()=>[n.loading?(e(),u(y,{key:0,indeterminate:``,color:`primary`})):(e(),u(se,{key:1,onSubmit:a[8]||=g(()=>{},[`prevent`])},{default:d(()=>[h(K,{"fixed-tabs":``,modelValue:n.tab,"onUpdate:modelValue":a[6]||=e=>n.tab=e},{default:d(()=>[h(W,{value:`element`,class:r({changed:n.dirty,error:n.error})},{default:d(()=>[m(t(n.$gettext(`Element`)),1)]),_:1},8,[`class`]),h(W,{value:`refs`},{default:d(()=>[m(t(n.$gettext(`Used by`)),1)]),_:1})]),_:1},8,[`modelValue`]),h(U,{modelValue:n.tab,"onUpdate:modelValue":a[7]||=e=>n.tab=e,touch:!1},{default:d(()=>[h(G,{value:`element`},{default:d(()=>[h(b,{"onUpdate:item":_.itemUpdated,onError:_.errorUpdated,assets:n.assets,item:c.item},null,8,[`onUpdate:item`,`onError`,`assets`,`item`])]),_:1}),h(G,{value:`refs`},{default:d(()=>[h(x,{item:c.item},null,8,[`item`])]),_:1})]),_:1},8,[`modelValue`])]),_:1}))]),_:1},8,[`aria-label`]),h(S,{item:c.item},null,8,[`item`]),(e(),u(o,{to:`body`},[h(C,{modelValue:n.vhistory,"onUpdate:modelValue":a[9]||=e=>n.vhistory=e,readonly:!l.user.can(`element:save`),current:_.historyCurrent,load:_.loadVersions,onRevert:_.revertVersion,onApply:_.apply,onUse:a[10]||=e=>_.use(e)},null,8,[`modelValue`,`readonly`,`current`,`load`,`onRevert`,`onApply`]),h(w,{modelValue:n.vchanged,"onUpdate:modelValue":a[11]||=e=>n.vchanged=e,changed:n.changed,targets:_.changeTargets,onResolve:a[12]||=e=>n.dirty=!0},null,8,[`modelValue`,`changed`,`targets`])]))],64)}var xe=k(ye,[[`render`,be]]);export{xe as default};