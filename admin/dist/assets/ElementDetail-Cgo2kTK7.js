const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./ChangesDialog-DoI38MsW.js","./charts-D3TmX40N.js","./rolldown-runtime-QTnfLwEv.js","./index-D7PaFGjO.js","./graphql-REqfkFwC.js","./vue-router-CJqHKYDY.js","./layout-CL79_-96.js","./VDefaultsProvider-CpKxBqlW.js","./transition-DkFadE4K.js","./VOverlay-CI44FUdU.js","./dimensions-CeCls-bn.js","./deepEqual-D5E4Pzwh.js","./lazy-D8UjMt6q.js","./router-xm0b9IIR.js","./VOverlay-DG-PukqI.css","./VBadge-B_ew7k_M.js","./VIcon-CaEd7GYo.js","./VIcon-DTxGzRyY.css","./rounded-BJI7KjEa.js","./loader-DeU4w-lV.js","./loader-BBmTwUdl.css","./VBadge-CZSms9Md.css","./framework-DM0GxE9c.js","./forwardRefs-RiU8Uqxp.js","./position-MRQh1NaW.js","./index-DBC1K6kz.css","./VChip-C40fF5_S.js","./VSlideGroup-DPXGSXCY.js","./VSlideGroup-9_hX0lAw.css","./VChip-CMsQ_jbl.css","./ChangesDialog-Wfy_IQRQ.css","./HistoryDialog-C876ex8N.js","./VCheckbox-DPilB94E.js","./VInput-DmctS4k0.js","./VInput-BsP73X2A.css","./VCheckboxBtn-B5xopxOw.js","./VSelectionControl-jL1NiPFx.js","./VSelectionControl-DCyuVH7W.css","./VCheckbox-ChRKi1kj.css","./HistoryDialog-B7chWXjp.css","./ai-Tvhxhoe5.js","./audio-M0-QB1fe.js"])))=>i.map(i=>d[i]);
import{$t as e,Cn as t,D as n,Hn as r,J as i,K as a,P as o,R as s,Ut as c,Y as l,Yt as u,Zt as d,nt as f,q as p,rt as m,sr as ee,tt as h,ur as g}from"./charts-D3TmX40N.js";import{s as _}from"./graphql-REqfkFwC.js";import{i as v}from"./loader-DeU4w-lV.js";import{a as y,t as te}from"./VMain-96lDw6JI.js";import{n as b,t as x}from"./VRow-BY2gb2JB.js";import{$n as S,Bn as C,Cn as w,In as T,Nn as E,On as D,Rn as O,Sn as k,Tn as ne,_n as re,b as A,bn as ie,fn as ae,hn as oe,pn as j,vn as M,xn as N,yn as P}from"./index-D7PaFGjO.js";import{a as F,c as I,i as L,n as R,o as z,r as B,t as V}from"./version-CdZPP6gC.js";import{t as H}from"./Fields-CVjEnOIW.js";import{t as U}from"./VSelect-CO-go9C1.js";import{t as W}from"./VSheet-BNtV1ccE.js";import{t as G}from"./VTextField-DdsaBInU.js";import{a as K,o as q,r as J,t as se}from"./VTabs-BSYj_abA.js";import{i as Y,n as X,r as Z,t as ce}from"./VExpansionPanels-BgdNbpOV.js";import{t as Q}from"./VTable-42HKEkqJ.js";import{t as le}from"./VForm-CQ12qBcx.js";var ue=_`
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
`,de={props:{item:{type:Object,required:!0}},emits:[],data:()=>({panel:[0,1,2],versions:{},element:{}}),setup(){return{user:k()}},beforeUnmount(){this.versions=null,this.element=null},methods:{mapVersion(e){let t=e.versionable_type.slice(e.versionable_type.lastIndexOf(`\\`)+1);return{id:e.versionable_id,type:t,published:e.published?this.$gettext(`yes`):e.publish_at?new Date(e.publish_at).toLocaleDateString():this.$gettext(`no`)}}},watch:{item:{immediate:!0,handler(e){!e.id||!this.user.can(`element:view`)||this.$apollo.query({query:ue,fetchPolicy:`no-cache`,variables:{id:e.id}}).then(e=>{if(e.errors)throw e.errors;let t=e.data?.element||{};this.element=Object.freeze({...t,bypages:Object.freeze((t.bypages||[]).map(e=>Object.freeze(e)))}),this.versions=Object.freeze((e.data?.element?.byversions||[]).map(e=>Object.freeze(this.mapVersion(e))).filter(e=>this.user.can(e.type.toLowerCase()+`:view`)))}).catch(t=>{this.$log(`ElementDetailRef::watch(item): Error fetching element`,e,t)})}}}};function fe(e,n,r,s,d,m){return c(),p(y,null,{default:t(()=>[f(W,{class:`scroll`},{default:t(()=>[f(ce,{modelValue:e.panel,"onUpdate:modelValue":n[0]||=t=>e.panel=t,elevation:`0`,multiple:``},{default:t(()=>[e.element.bypages?.length&&s.user.can(`page:view`)?(c(),p(X,{key:0},{default:t(()=>[f(Z,null,{default:t(()=>[h(g(e.$gettext(`Shared elements`)),1)]),_:1}),f(Y,null,{default:t(()=>[f(Q,{density:`comfortable`,hover:``},{default:t(()=>[a(`thead`,null,[a(`tr`,null,[a(`th`,null,g(e.$gettext(`ID`)),1),a(`th`,null,g(e.$gettext(`URL`)),1),a(`th`,null,g(e.$gettext(`Name`)),1)])]),a(`tbody`,null,[(c(!0),l(o,null,u(e.element.bypages,e=>(c(),l(`tr`,{key:e.id},[a(`td`,null,g(e.id),1),a(`td`,null,g(e.path),1),a(`td`,null,g(e.name),1)]))),128))])]),_:1})]),_:1})]),_:1})):i(``,!0),e.versions?.length?(c(),p(X,{key:1},{default:t(()=>[f(Z,null,{default:t(()=>[...n[1]||=[h(`Versions`,-1)]]),_:1}),f(Y,null,{default:t(()=>[f(Q,{density:`comfortable`,hover:``},{default:t(()=>[a(`thead`,null,[a(`tr`,null,[a(`th`,null,g(e.$gettext(`ID`)),1),a(`th`,null,g(e.$gettext(`Type`)),1),a(`th`,null,g(e.$gettext(`Published`)),1)])]),a(`tbody`,null,[(c(!0),l(o,null,u(e.versions,e=>(c(),l(`tr`,{key:e.id},[a(`td`,null,g(e.id),1),a(`td`,null,g(e.type),1),a(`td`,null,g(e.published),1)]))),128))])]),_:1})]),_:1})]),_:1})):i(``,!0)]),_:1},8,[`modelValue`])]),_:1})]),_:1})}var pe=A(de,[[`render`,fe],[`__scopeId`,`data-v-956e267e`]]),$={components:{Fields:H},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:[`update:item`,`error`],setup(){let e=re(),t=ie(),n=N(),r=k();return{app:ae(),user:r,languages:e,schemas:t,side:n,locales:O}},computed:{readonly(){return!this.user.can(`element:save`)}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit(`update:item`,this.item)}}};function me(e,n,r,i,a,o){let s=d(`Fields`);return c(),p(y,null,{default:t(()=>[f(W,{class:`scroll`},{default:t(()=>[f(x,null,{default:t(()=>[f(b,{cols:`12`,md:`6`},{default:t(()=>[f(G,{ref:`name`,readonly:o.readonly,modelValue:r.item.name,"onUpdate:modelValue":n[0]||=e=>o.update(`name`,e),variant:`underlined`,label:e.$gettext(`Name`),counter:`255`,maxlength:`255`},null,8,[`readonly`,`modelValue`,`label`])]),_:1}),f(b,{cols:`12`,md:`6`},{default:t(()=>[f(U,{ref:`lang`,items:i.locales(!0),readonly:o.readonly,modelValue:r.item.lang,"onUpdate:modelValue":n[1]||=e=>o.update(`lang`,e),variant:`underlined`,label:e.$gettext(`Language`)},null,8,[`items`,`readonly`,`modelValue`,`label`])]),_:1})]),_:1}),f(x,null,{default:t(()=>[f(b,{cols:`12`},{default:t(()=>[f(s,{ref:`field`,data:r.item.data,"onUpdate:data":n[2]||=e=>r.item.data=e,files:r.item.files,"onUpdate:files":n[3]||=e=>r.item.files=e,fields:o.fields(r.item.type),readonly:o.readonly,assets:r.assets,type:r.item.type,onError:n[4]||=t=>e.$emit(`error`,t),onChange:n[5]||=t=>e.$emit(`update:item`,r.item)},null,8,[`data`,`files`,`fields`,`readonly`,`assets`,`type`])]),_:1})]),_:1})]),_:1})]),_:1})}var he=A($,[[`render`,me],[`__scopeId`,`data-v-35651616`]]),ge=m(()=>S(()=>import(`./ChangesDialog-DoI38MsW.js`),__vite__mapDeps([0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30]),import.meta.url)),_e=m(()=>S(()=>import(`./HistoryDialog-C876ex8N.js`),__vite__mapDeps([31,1,2,6,7,10,16,17,18,3,4,5,8,9,11,12,13,14,15,19,20,21,22,23,24,25,26,27,28,29,32,33,34,35,36,37,38,39]),import.meta.url)),ve=_`
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
`,ye=_`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,be=_`
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
`,xe={components:{AsideMeta:F,ChangesDialog:ge,DetailAppBar:L,HistoryDialog:_e,ElementDetailRefs:pe,ElementDetailItem:he},props:{item:{type:Object,required:!0},stacked:{type:Boolean,default:!1}},provide(){return{write:this.writeText,translate:this.translateText}},data:()=>({assets:{},changed:null,destroyed:!1,dirty:!1,echoCleanup:null,echoPromise:null,error:!1,latestId:null,loading:!0,publishAt:null,publishTime:null,publishing:!1,saving:!1,vchanged:!1,vhistory:!1,tab:`element`}),setup(){let e=oe(),t=M();return{dirtyStore:e,side:N(),user:k(),messages:t,viewStack:w(),changes:j()}},created(){if(this.dirtyStore.register(()=>this.save(!0)),!this.item?.id||!this.user.can(`element:view`)){this.loading=!1;return}this.reload().then(e=>{e&&D(this,`element`,this.item.id,()=>this.reload(),()=>!this.dirty&&this.user.can(`element:view`))})},beforeUnmount(){this.dirtyStore.unregister(),this.side.$reset(),this.assets=r({}),this.destroyed=!0,this.changed=null,ne(this)},computed:{changeTargets(){return r({data:this.item})},subpanels(){return P().subpanels.element||{}},hasConflict(){return I(this.changed)},historyCurrent(){let e=this.item,t=new Set(e.files||[]),n={};for(let e in this.assets)t.has(e)&&(n[e]=this.assets[e]);return r({data:Object.freeze({lang:e.lang,type:e.type,name:e.name,data:e.data}),files:r(n)})}},methods:{reload(){return V(this,ve,`element`,this.$gettext(`Error fetching element`),e=>{Object.assign(this.item,C(e.latest?.data)),this.item.published=e.latest?.published,this.item.editor=e.latest?.editor,this.item.updated_at=e.latest?.created_at,this.latestId=e.latest?.id;let t=[],n={};for(let r of e.latest?.files||e.files||[])n[r.id]={...r,previews:E(r.previews)},t.push(r.id);this.assets=r(n),this.item.files=t},()=>!this.dirty)},apply(e){Object.assign(this.item,e),this.dirty=!0,this.vhistory=!1},errorUpdated(e){this.error=e},files(e){let t={};for(let n of e)t[n.id]={...n,previews:E(n.previews)};return t},itemUpdated(){this.$emit(`update:item`,this.item),this.dirty=!0},loadVersions(){return this.versions(this.item.id)},publish(e=null){B(this,`element`,{success:this.$gettext(`Element published successfully`),scheduled:e=>this.$gettext(`Element scheduled for publishing at %{date}`,{date:e.toLocaleDateString()}),error:this.$gettext(`Error publishing element`)},e)},published(){this.publish(R(this.publishAt,this.publishTime))},reset(){this.dirty=!1,this.changed=null,this.error=!1},revertVersion(e){this.use(e),this.reset()},save(e=!1){return this.user.can(`element:save`)?this.error?(this.messages.add(this.$gettext(`There are invalid fields, please resolve the errors first`),`error`),Promise.resolve(!1)):this.dirty?(this.item.name||(this.item.name=this.title(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:ye,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.latestId}}).then(t=>{if(t.errors)throw t.errors;let n=t.data?.saveElement,i=n?.changed?r(C(n.changed)):null;(i?.latest?.id||n?.latest?.id)&&(this.latestId=i?.latest?.id??n.latest.id),z(this,i,this.$gettext(`Element saved successfully`),e);let a=n?.latest;return this.item.published=a?.published??!1,this.item.publish_at=a?.publish_at??null,this.item.editor=a?.editor??this.item.editor,this.item.updated_at=a?.created_at??this.item.updated_at,this.item.latestId=this.latestId,this.changes.notify(`element`,this.item),!0}).catch(e=>{this.messages.add(this.$gettext(`Error saving element`)+`:
`+e,`error`),this.$log(`ElementDetail::save(): Error saving element`,e)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve(!1))},title(e){return T(e)},writeText(e,t=[],n=[]){return Array.isArray(t)||(t=[t]),t.push(`element data as JSON: `+JSON.stringify(this.item.data)),t.push(`required output language: `+(this.item.lang||`en`)),S(async()=>{let{write:e}=await import(`./ai-Tvhxhoe5.js`);return{write:e}},__vite__mapDeps([40,4,2,3,1,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,41]),import.meta.url).then(({write:r})=>r(e,t,n))},use(e){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0},translateText(e,t,n=null){return S(async()=>{let{translate:e}=await import(`./ai-Tvhxhoe5.js`);return{translate:e}},__vite__mapDeps([40,4,2,3,1,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,41]),import.meta.url).then(({translate:r})=>r(e,t,n||this.item.lang))},versions(e){return this.user.can(`element:view`)?e?this.$apollo.query({query:be,fetchPolicy:`no-cache`,variables:{id:e}}).then(e=>{if(e.errors||!e.data.element)throw e;return(e.data.element.versions||[]).map(e=>Object.freeze({...e,data:E(e.data),files:Object.freeze(this.files(e.files||[]))}))}).catch(t=>{this.messages.add(this.$gettext(`Error fetching element versions`)+`:
`+t,`error`),this.$log(`ElementDetail::versions(): Error fetching element versions`,e,t)}):Promise.resolve([]):(this.messages.add(this.$gettext(`Permission denied`),`error`),Promise.resolve([]))}},watch:{dirty(e){this.dirtyStore.set(e)}}};function Se(r,i,a,m,_,y){let b=d(`DetailAppBar`),x=d(`ElementDetailItem`),S=d(`ElementDetailRefs`),C=d(`AsideMeta`),w=d(`HistoryDialog`),T=d(`ChangesDialog`);return c(),l(o,null,[f(b,{type:`element`,label:r.$gettext(`Element`),name:a.item.name,stacked:a.stacked,dirty:r.dirty,error:r.error,conflict:y.hasConflict,changed:r.changed,published:a.item.published,"has-latest":!!r.latestId,saving:r.saving,publishing:r.publishing,"publish-at":r.publishAt,"onUpdate:publishAt":i[0]||=e=>r.publishAt=e,"publish-time":r.publishTime,"onUpdate:publishTime":i[1]||=e=>r.publishTime=e,onSave:i[2]||=e=>y.save(),onPublish:i[3]||=e=>y.publish(),onSchedule:y.published,onHistory:i[4]||=e=>r.vhistory=!0,onChanges:i[5]||=e=>r.vchanged=!0},null,8,[`label`,`name`,`stacked`,`dirty`,`error`,`conflict`,`changed`,`published`,`has-latest`,`saving`,`publishing`,`publish-at`,`publish-time`,`onSchedule`]),f(te,{class:`element-details`,"aria-label":r.$gettext(`Element`)},{default:t(()=>[r.loading?(c(),p(v,{key:0,indeterminate:``,color:`primary`})):(c(),p(le,{key:1,onSubmit:i[8]||=n(()=>{},[`prevent`])},{default:t(()=>[f(se,{"fixed-tabs":``,modelValue:r.tab,"onUpdate:modelValue":i[6]||=e=>r.tab=e},{default:t(()=>[f(q,{value:`element`,class:ee({changed:r.dirty,error:r.error})},{default:t(()=>[h(g(r.$gettext(`Element`)),1)]),_:1},8,[`class`]),f(q,{value:`refs`},{default:t(()=>[h(g(r.$gettext(`Used by`)),1)]),_:1}),(c(!0),l(o,null,u(y.subpanels,(e,n)=>(c(),p(q,{key:n,value:`ext-`+n},{default:t(()=>[h(g(e.label),1)]),_:2},1032,[`value`]))),128))]),_:1},8,[`modelValue`]),f(K,{modelValue:r.tab,"onUpdate:modelValue":i[7]||=e=>r.tab=e,touch:!1},{default:t(()=>[f(J,{value:`element`},{default:t(()=>[f(x,{"onUpdate:item":y.itemUpdated,onError:y.errorUpdated,assets:r.assets,item:a.item},null,8,[`onUpdate:item`,`onError`,`assets`,`item`])]),_:1}),f(J,{value:`refs`},{default:t(()=>[f(S,{item:a.item},null,8,[`item`])]),_:1}),(c(!0),l(o,null,u(y.subpanels,(n,i)=>(c(),p(J,{key:i,value:`ext-`+i},{default:t(()=>[(c(),p(e(n.component),{item:a.item,assets:r.assets},null,8,[`item`,`assets`]))]),_:2},1032,[`value`]))),128))]),_:1},8,[`modelValue`])]),_:1}))]),_:1},8,[`aria-label`]),f(C,{item:a.item},null,8,[`item`]),(c(),p(s,{to:`body`},[f(w,{modelValue:r.vhistory,"onUpdate:modelValue":i[9]||=e=>r.vhistory=e,readonly:!m.user.can(`element:save`),current:y.historyCurrent,load:y.loadVersions,onRevert:y.revertVersion,onApply:y.apply,onUse:i[10]||=e=>y.use(e)},null,8,[`modelValue`,`readonly`,`current`,`load`,`onRevert`,`onApply`]),f(T,{modelValue:r.vchanged,"onUpdate:modelValue":i[11]||=e=>r.vchanged=e,changed:r.changed,targets:y.changeTargets,onResolve:i[12]||=e=>r.dirty=!0},null,8,[`modelValue`,`changed`,`targets`])]))],64)}var Ce=A(xe,[[`render`,Se]]);export{Ce as default};