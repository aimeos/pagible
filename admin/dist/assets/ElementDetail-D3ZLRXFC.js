const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-Bz5Qvst6.js","./charts-CjXvq1zh.js","./rolldown-runtime-QTnfLwEv.js","./index-Cl_lqxlf.js","./dimensions-Cj53uB-V.js","./en-BH83F3-h.js","./graphql-CQpNdpSk.js","./VDefaultsProvider-BxbSI2CX.js","./transition-7S8Rs99o.js","./VOverlay-Cy8xgXtu.js","./deepEqual-DpGg6utq.js","./display-C-n9w4sK.js","./lazy-bf8Pv2R_.js","./router-D-Uogn79.js","./VOverlay-DG-PukqI.css","./VBadge-Cp9v_ewg.js","./loader-zrTQzX-0.js","./VIcon-BVdffwF0.js","./VIcon-DTxGzRyY.css","./rounded-DdTDohEf.js","./loader-BBmTwUdl.css","./VBadge-CZSms9Md.css","./forwardRefs-RiU8Uqxp.js","./position-DYrZg6Ta.js","./index-DBC1K6kz.css","./VChip-DZoiEHa8.js","./VSlideGroup-16aDehXw.js","./VSlideGroup-9_hX0lAw.css","./VChip-CMsQ_jbl.css","./ChangesDialog-Wfy_IQRQ.css","./HistoryDialog-B6oxODbV.js","./VCheckbox-ChJ0Ynac.js","./VInput-BTGw3iFT.js","./VInput-BsP73X2A.css","./VCheckboxBtn-CV33xyR2.js","./VSelectionControl-B1Ed30p4.js","./VSelectionControl-DCyuVH7W.css","./VCheckbox-ChRKi1kj.css","./HistoryDialog-B7chWXjp.css","./ai-BykAGBbZ.js","./audio-M0-QB1fe.js"])))=>i.map(i=>d[i]);
import{B as e,Bt as t,H as n,Lt as r,W as i,b as a,c as o,g as s,h as c,lt as l,m as u,nt as d,o as f,p,v as m,y as h}from"./charts-CjXvq1zh.js";import{in as ee}from"./dimensions-Cj53uB-V.js";import{r as g}from"./graphql-CQpNdpSk.js";import{i as _}from"./loader-zrTQzX-0.js";import{a as v,t as te}from"./VMain-DsByiRkc.js";import{n as y,t as b}from"./VRow-D96ps0V2.js";import{Dn as x,En as S,Hn as C,Kn as w,On as T,Pn as E,Sn as D,Tn as O,Wn as ne,ar as k,b as A,bn as re,jn as ie,kn as ae,wn as j,yn as M,zn as N}from"./index-Cl_lqxlf.js";import{a as P,c as F,i as I,n as L,o as R,r as z,t as B}from"./version-CanAR5k1.js";import{t as V}from"./Fields-DNkVuWW_.js";import{t as H}from"./VSelect-CdQ1Yp4x.js";import{t as U}from"./VSheet-DZJQbhkd.js";import{t as W}from"./VTextField-CB9tDB9n.js";import{a as G,o as K,r as q,t as J}from"./VTabs-l-iFkNrd.js";import{i as Y,n as X,r as Z,t as oe}from"./VExpansionPanels--6YKT5ZD.js";import{t as Q}from"./VTable-D1DV5AX_.js";import{t as se}from"./VForm-C0sDxoaO.js";var ce=g`
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
`,le={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:T()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){let t=e.versionable_type.slice(e.versionable_type.lastIndexOf(`\\`)+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext(`yes`):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext(`no`)}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can(`element:view`)||this.$apollo.query({query:ce,fetchPolicy:`no-cache`,variables:{id:e.id}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.element||{};this.element=Object.freeze({...t,bypages:Object.freeze((t.bypages||[]).map(e=>Object.freeze(e)))}),this.versions=Object.freeze((e.data?.element?.byversions||[]).map(e=>Object.freeze(this.mapVersion(e))).filter(e=>this.user.can(e.type.toLowerCase()+`:view`)))}).catch(t=>{this.$log(`ElementDetailRef::watch(item): Error fetching element`,e,t)})}}}};function ue(r,i,a,o,l,ee){return e(),u(v,null,{default:d(()=>[h(U,{class:`scroll`},{default:d(()=>[h(oe,{modelValue:r.panel,"onUpdate:modelValue":i[0]||=e=>r.panel=e,elevation:`0`,multiple:``},{default:d(()=>[r.element.bypages?.length&&o.user.can(`page:view`)?(e(),u(X,{key:0},{default:d(()=>[h(Z,null,{default:d(()=>[m(t(r.$gettext(`Shared elements`)),1)]),_:1}),h(Y,null,{default:d(()=>[h(Q,{density:`comfortable`,hover:``},{default:d(()=>[p(`thead`,null,[p(`tr`,null,[p(`th`,null,t(r.$gettext(`ID`)),1),p(`th`,null,t(r.$gettext(`URL`)),1),p(`th`,null,t(r.$gettext(`Name`)),1)])]),p(`tbody`,null,[(e(!0),s(f,null,n(r.element.bypages,n=>(e(),s(`tr`,{key:n.id},[p(`td`,null,t(n.id),1),p(`td`,null,t(n.path),1),p(`td`,null,t(n.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):c(``,!0),r.versions?.length?(e(),u(X,{key:1},{default:d(()=>[h(Z,null,{default:d(()=>[...i[1]||=[m(`Versions`,-1)]]),_:1}),h(Y,null,{default:d(()=>[h(Q,{density:`comfortable`,hover:``},{default:d(()=>[p(`thead`,null,[p(`tr`,null,[p(`th`,null,t(r.$gettext(`ID`)),1),p(`th`,null,t(r.$gettext(`Type`)),1),p(`th`,null,t(r.$gettext(`Published`)),1)])]),p(`tbody`,null,[(e(!0),s(f,null,n(r.versions,n=>(e(),s(`tr`,{key:n.id},[p(`td`,null,t(n.id),1),p(`td`,null,t(n.type),1),p(`td`,null,t(n.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):c(``,!0)]),_:1},8,[`modelValue`])]),_:1})]),_:1})}var de=A(le,[[`render`,ue],[`__scopeId`,`data-v-956e267e`]]),fe={components:{Fields:V},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:[`update:item`,`error`],setup(){let e=j(),t=S(),n=x(),r=T();return{app:M(),user:r,languages:e,schemas:t,side:n,locales:ne}},computed:{readonly(){return!this.user.can(`element:save`)}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit(`update:item`,this.item)}}};function pe(t,n,r,a,o,s){let c=i(`Fields`);return e(),u(v,null,{default:d(()=>[h(U,{class:`scroll`},{default:d(()=>[h(b,null,{default:d(()=>[h(y,{cols:`12`,md:`6`},{default:d(()=>[h(W,{ref:`name`,readonly:s.readonly,modelValue:r.item.name,"onUpdate:modelValue":n[0]||=e=>s.update(`name`,e),variant:`underlined`,label:t.$gettext(`Name`),counter:`255`,maxlength:`255`},null,8,[`readonly`,`modelValue`,`label`])]),_:1}),h(y,{cols:`12`,md:`6`},{default:d(()=>[h(H,{ref:`lang`,items:a.locales(!0),readonly:s.readonly,modelValue:r.item.lang,"onUpdate:modelValue":n[1]||=e=>s.update(`lang`,e),variant:`underlined`,label:t.$gettext(`Language`)},null,8,[`items`,`readonly`,`modelValue`,`label`])]),_:1})]),_:1}),h(b,null,{default:d(()=>[h(y,{cols:`12`},{default:d(()=>[h(c,{ref:`field`,data:r.item.data,"onUpdate:data":n[2]||=e=>r.item.data=e,files:r.item.files,"onUpdate:files":n[3]||=e=>r.item.files=e,fields:s.fields(r.item.type),readonly:s.readonly,assets:r.assets,type:r.item.type,onError:n[4]||=e=>t.$emit(`error`,e),onChange:n[5]||=e=>t.$emit(`update:item`,r.item)},null,8,[`data`,`files`,`fields`,`readonly`,`assets`,`type`])]),_:1})]),_:1})]),_:1})]),_:1})}var $=A(fe,[[`render`,pe],[`__scopeId`,`data-v-35651616`]]),me=a(()=>k(()=>import(`./ChangesDialog-Bz5Qvst6.js`),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29]),import.meta.url)),he=a(()=>k(()=>import(`./HistoryDialog-B6oxODbV.js`),__vite__mapDeps([30,1,2,4,5,7,17,18,19,3,6,8,9,10,11,12,13,14,15,16,20,21,22,23,24,25,26,27,28,31,32,33,34,35,36,37,38]),import.meta.url)),ge=g`
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
`,_e=g`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,ve=g`
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
`,ye={components:{AsideMeta:P,ChangesDialog:me,DetailAppBar:I,HistoryDialog:he,ElementDetailRefs:de,ElementDetailItem:$},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:`element`}),setup(){let e=D(),t=O();return{dirtyStore:e,side:x(),user:T(),messages:t,viewStack:ae(),changes:re()}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can(`element:view`)){this.loading=!1;return}this.reload().then(e=>{e&&E(this,`element`,this.item.id,()=>this.reload(),()=>!this.dirty&&this.user.can(`element:view`))})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=l({}),this.destroyed=!0,this.changed=null,ie(this)},computed:{changeTargets(){return l({data:this.item})},hasConflict(){return F(this.changed)},historyCurrent(){let e=this.item,t=new Set(e.files||[]),n={};for(let e in this.assets)t.has(e)&&(n[e]=this.assets[e]);return l({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:l(n)})}},methods:{reload(){return B(this,ge,`element`,this.$gettext(`Error fetching element`),e=>{Object.assign(this.item,w(e.latest?.data)),this.item.published=e.latest?.published,this.item.editor=e.latest?.editor,this.item.updated_at=e.latest?.created_at,this.latestId=e.latest?.id;let t=[],n={};for(let r of e.latest?.files||e.files||[])n[r.id]={...r,previews:N(r.previews)},t.push(r.id);this.assets=l(n),this.item.files=t},()=>!this.dirty)},apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},files(e){let t={};for(let n of e)t[n.id]={...n,previews:N(n.previews)};return t},itemUpdated(){this.$emit(`update:item`,this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){z(this,`element`,{success:this.$gettext(`Element published successfully`),scheduled:e=>this.$gettext(`Element scheduled for publishing at %{date}`,{date:e.toLocaleDateString()}),error:this.$gettext(`Error publishing element`)},e)},published(){this.publish(L(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can(`element:save`)?this.error?(this.messages.add(this.$gettext(`There are invalid fields, please resolve the errors first`),`error`),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:_e,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;let n=t.data?.saveElement,r=n?.changed?l(w(n.changed)):null;(r?.latest?.id||n?.latest?.id)&&(this.latestId=r?.latest?.id??n.latest.id),R(this,r,this.$gettext(`Element saved successfully`),e);let i=n?.latest;return this.item.published=i?.published??!1,this.item.publish_at=i?.publish_at??null,this.item.editor=i?.editor??this.item.editor,this.item.updated_at=i?.created_at??this.item.updated_at,this.item.latestId=this.latestId,this.changes.notify(`element`,this.item),!0}).catch(e=>{this.messages.add(this.$gettext(`Error saving element`)+`:
`+e,`error`),this.$log(`ElementDetail::save(): Error saving element`,e)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve(!1))},title(e){return C(e)},writeText(e,t=[],n=[]){return Array.isArray(t)||(t=[t]),t.push(`element data as JSON: `+JSON.stringify(this.item.data)),t.push(`required output language: `+(this.item.lang||`en`)),k(async()=>{let{write:e}=await import(`./ai-BykAGBbZ.js`);return{write:e}},__vite__mapDeps([39,6,2,3,1,4,5,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,40]),import.meta.url).then(({write:r})=>r(e,t,n))},use(e){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0},translateText(e,t,n=null){return k(async()=>{let{translate:e}=await import(`./ai-BykAGBbZ.js`);return{translate:e}},__vite__mapDeps([39,6,2,3,1,4,5,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,40]),import.meta.url).then(({translate:r})=>r(e,t,n||this.item.lang))},versions(e){return this.user.can(`element:view`)?e?this.$apollo.query({query:ve,fetchPolicy:`no-cache`,variables:{id:e}}).then(e=>{if(e.errors||!e.data.element)throw e;return(e.data.element.versions||[]).map(e=>Object.freeze({...e,data:N(e.data),files:Object.freeze(this.files(e.files||[]))}))}).catch(t=>{this.messages.add(this.$gettext(`Error fetching element versions`)+`:
`+t,`error`),this.$log(`ElementDetail::versions(): Error fetching element versions`,e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function be(n,a,c,l,p,g){let v=i(`DetailAppBar`),y=i(`ElementDetailItem`),b=i(`ElementDetailRefs`),x=i(`AsideMeta`),S=i(`HistoryDialog`),C=i(`ChangesDialog`);return e(),s(f,null,[h(v,{type:`element`,label:n.$gettext(`Element`),name:c.item.name,stacked:c.stacked,dirty:n.dirty,error:n.error,conflict:g.hasConflict,changed:n.changed,published:c.item.published,"has-latest":!!n.latestId,saving:n.saving,publishing:n.publishing,"publish-at":n.publishAt,"onUpdate:publishAt":a[0]||=e=>n.publishAt=e,"publish-time":n.publishTime,"onUpdate:publishTime":a[1]||=e=>n.publishTime=e,onSave:a[2]||=e=>g.save(),onPublish:a[3]||=e=>g.publish(),onSchedule:g.published,onHistory:a[4]||=e=>n.vhistory=!0,onChanges:a[5]||=e=>n.vchanged=!0},null,8,[`label`,`name`,`stacked`,`dirty`,`error`,`conflict`,`changed`,`published`,`has-latest`,`saving`,`publishing`,`publish-at`,`publish-time`,`onSchedule`]),h(te,{class:`element-details`,"aria-label":n.$gettext(`Element`)},{default:d(()=>[n.loading?(e(),u(_,{key:0,indeterminate:``,color:`primary`})):(e(),u(se,{key:1,onSubmit:a[8]||=ee(()=>{},[`prevent`])},{default:d(()=>[h(J,{"fixed-tabs":``,modelValue:n.tab,"onUpdate:modelValue":a[6]||=e=>n.tab=e},{default:d(()=>[h(K,{value:`element`,class:r({changed:n.dirty,error:n.error})},{default:d(()=>[m(t(n.$gettext(`Element`)),1)]),_:1},8,[`class`]),h(K,{value:`refs`},{default:d(()=>[m(t(n.$gettext(`Used by`)),1)]),_:1})]),_:1},8,[`modelValue`]),h(G,{modelValue:n.tab,"onUpdate:modelValue":a[7]||=e=>n.tab=e,touch:!1},{default:d(()=>[h(q,{value:`element`},{default:d(()=>[h(y,{"onUpdate:item":g.itemUpdated,onError:g.errorUpdated,assets:n.assets,item:c.item},null,8,[`onUpdate:item`,`onError`,`assets`,`item`])]),_:1}),h(q,{value:`refs`},{default:d(()=>[h(b,{item:c.item},null,8,[`item`])]),_:1})]),_:1},8,[`modelValue`])]),_:1}))]),_:1},8,[`aria-label`]),h(x,{item:c.item},null,8,[`item`]),(e(),u(o,{to:`body`},[h(S,{modelValue:n.vhistory,"onUpdate:modelValue":a[9]||=e=>n.vhistory=e,readonly:!l.user.can(`element:save`),current:g.historyCurrent,load:g.loadVersions,onRevert:g.revertVersion,onApply:g.apply,onUse:a[10]||=e=>g.use(e)},null,8,[`modelValue`,`readonly`,`current`,`load`,`onRevert`,`onApply`]),h(C,{modelValue:n.vchanged,"onUpdate:modelValue":a[11]||=e=>n.vchanged=e,changed:n.changed,targets:g.changeTargets,onResolve:a[12]||=e=>n.dirty=!0},null,8,[`modelValue`,`changed`,`targets`])]))],64)}var xe=A(ye,[[`render`,be]]);export{xe as default};