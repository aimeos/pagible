import{At as e,F as t,J as n,O as r,Qt as i,Un as a,Wt as o,X as s,Xt as c,Y as l,cr as u,dr as d,en as f,nt as p,rt as m,wn as h,z as g}from"./charts-qBJJ9lq9.js";import{V as _,g as v,v as y,z as b}from"./graphql-BUG_GYSd.js";import{s as x}from"./graphql-BRoDkLYC.js";import{S,x as C}from"./i18n-DRPnG9HE.js";import{t as w}from"./VSelect-DJxDYfPm.js";import{i as T}from"./loader-CPW8Dr6s.js";import{t as E}from"./VContainer-DuKpnkhw.js";import{n as D,t as O}from"./VRow-DlDgPrx0.js";import{M as k,f as A}from"./index-C2M3Uhl_.js";import{o as j}from"./history-B6hvHFgF.js";import{a as M,o as N,r as P,t as F}from"./VTabs-T3FLznGW.js";import{t as I}from"./VSheet-C1uiCv2-.js";import{i as L,n as R}from"./files-BIiNSZI4.js";import{n as z,r as B,t as V}from"./detail-BC4Oy90b.js";import{t as H}from"./Fields-D46Xs9zX.js";import{t as U}from"./VMain-C8Bua_ku.js";import{t as W}from"./VForm-DiwyZmKu.js";import{t as G}from"./DetailRefs-CN9yiP1i.js";var K={components:{Fields:H},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>{}}},emits:[`update:item`,`error`],setup(){let e=v();return{user:y(),schemas:e,locales:_}},computed:{readonly(){return!this.user.can(`element:save`)}},methods:{fields(e){return e?this.schemas.content[e]?.fields?this.schemas.content[e]?.fields:(console.warn(`No definition of fields for "${e}" schemas`),[]):[]},update(e,t){this.item[e]=t,this.$emit(`update:item`,this.item)}}};function q(e,t,r,a,s,c){let l=i(`Fields`);return o(),n(E,null,{default:h(()=>[m(I,{class:`scroll`},{default:h(()=>[m(O,null,{default:h(()=>[m(D,{cols:`12`,md:`6`},{default:h(()=>[m(A,{ref:`name`,readonly:c.readonly,modelValue:r.item.name,"onUpdate:modelValue":t[0]||=e=>c.update(`name`,e),variant:`underlined`,label:e.$gettext(`Name`)+` ‒ `+e.$gettext(`Name to find the shared element in the element list`),counter:`255`,maxlength:`255`},null,8,[`readonly`,`modelValue`,`label`])]),_:1}),m(D,{cols:`12`,md:`6`},{default:h(()=>[m(w,{ref:`lang`,items:a.locales(!0),readonly:c.readonly,modelValue:r.item.lang,"onUpdate:modelValue":t[1]||=e=>c.update(`lang`,e),variant:`underlined`,label:e.$gettext(`Language`)+` ‒ `+e.$gettext(`Language of the element content, choose none if used in all languages`)},null,8,[`items`,`readonly`,`modelValue`,`label`])]),_:1})]),_:1}),m(O,null,{default:h(()=>[m(D,{cols:`12`},{default:h(()=>[m(l,{ref:`field`,data:r.item.data,"onUpdate:data":t[2]||=e=>r.item.data=e,files:r.item.files,"onUpdate:files":t[3]||=e=>r.item.files=e,fields:c.fields(r.item.type),readonly:c.readonly,assets:r.assets,type:r.item.type,onError:t[4]||=t=>e.$emit(`error`,t),onChange:t[5]||=t=>e.$emit(`update:item`,r.item)},null,8,[`data`,`files`,`fields`,`readonly`,`assets`,`type`])]),_:1})]),_:1})]),_:1})]),_:1})}var J=k(K,[[`render`,q],[`__scopeId`,`data-v-461aa0ba`]]),Y=x`
  ${R}
  query ($id: ID!) {
    element(id: $id) {
      id
      files {
        ...CmsFileFields
      }
      latest {
        id
        published
        data
        editor
        created_at
        files {
          ...CmsFileFields
        }
      }
    }
  }
`,X=x`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,Z=x`
  ${R}
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
          ...CmsFileFields
        }
      }
    }
  }
`,Q={extends:V,components:{...V.components,DetailRefs:G,ElementDetailItem:J},data:()=>({assets:{},latestId:null}),setup(){return{...z(`element`),schemas:v()}},created(){this.schemas.load()},beforeUnmount(){this.assets=a({})},computed:{changeTargets(){return a({data:this.item})},historyCurrent(){let e=this.item,t=new Set(e.files||[]),n=Object.fromEntries(Object.entries(this.assets).filter(([e])=>t.has(e)));return a({data:Object.freeze({data:e.data||{},scheduled:+!!e.publish_at,lang:e.lang,type:e.type,name:e.name}),files:a(n)})}},methods:{reload(){return this.reloadVersion(Y,this.$gettext(`Error fetching element`),e=>{Object.assign(this.item,S(e.latest?.data)),this.item.published=e.latest?.published,this.item.editor=e.latest?.editor,this.item.updated_at=e.latest?.created_at,this.latestId=e.latest?.id;let t=e.latest?.files||e.files||[];this.assets=a(L(t)),this.item.files=t.map(e=>e.id)},()=>!this.dirty)},apply(e,t){t&&(this.assets={...t.files,...this.assets}),Object.assign(this.item,e),`data`in e&&(this.item.files=j(this.item.data)),this.dirty=!0,this.vhistory=!1},files:L,save(e=!1){return this.saveable()?this.dirty?(this.item.name||(this.item.name=b(this.item.data)),this.saving=!0,this.$apollo.mutate({mutation:X,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.latestId}}).then(t=>{let n=t.data?.saveElement,r=n?.changed?a(S(n.changed)):null;return(r?.latest?.id||n?.latest?.id)&&(this.latestId=r?.latest?.id??n.latest.id),B(this,r,this.$gettext(`Element saved successfully`),e),this.item.latestId=this.latestId,this.saved(n?.latest),!0}).catch(e=>{this.messages.error(this.$gettext(`Error saving element`),e)}).finally(()=>{this.saving=!1})):Promise.resolve(!0):Promise.resolve(!1)},use(e,t=!1){Object.assign(this.item,e.data),this.assets=e.files||{},this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.dirty=!0,t&&this.reset()},versions(e){return this.loadVersions(Z,e,e=>Object.freeze({...e,data:C(e.data),files:Object.freeze(this.files(e.files||[]))}))}}};function $(a,_,v,y,b,x){let S=i(`DetailAppBar`),C=i(`ElementDetailItem`),w=i(`DetailRefs`),E=i(`AsideMeta`),D=i(`HistoryDialog`),O=i(`ChangesDialog`);return o(),s(t,null,[m(S,e(a.bar,{label:a.$gettext(`Element`),"has-latest":!!a.latestId}),null,16,[`label`,`has-latest`]),m(U,{class:`element-details`,"aria-label":a.$gettext(`Element`)},{default:h(()=>[a.loading?(o(),n(T,{key:0,indeterminate:``,color:`primary`})):(o(),n(W,{key:1,ref:`form`,onSubmit:_[3]||=r(()=>{},[`prevent`])},{default:h(()=>[m(F,{class:`detail-tabs`,"fixed-tabs":``,"hide-slider":``,modelValue:a.tab,"onUpdate:modelValue":_[0]||=e=>a.tab=e},{default:h(()=>[m(N,{value:`element`,class:u({changed:a.dirty,error:a.error})},{default:h(()=>[p(d(a.$gettext(`Element`)),1)]),_:1},8,[`class`]),m(N,{value:`refs`},{default:h(()=>[p(d(a.$gettext(`Used by`)),1)]),_:1}),(o(!0),s(t,null,c(a.subpanels,(e,t)=>(o(),n(N,{key:t,value:`ext-`+t},{default:h(()=>[p(d(a.label(e)),1)]),_:2},1032,[`value`]))),128))]),_:1},8,[`modelValue`]),m(M,{modelValue:a.tab,"onUpdate:modelValue":_[2]||=e=>a.tab=e,touch:!1},{default:h(()=>[m(P,{value:`element`},{default:h(()=>[m(C,{"onUpdate:item":a.itemUpdated,onError:_[1]||=e=>a.error=e,assets:a.assets,item:a.item},null,8,[`onUpdate:item`,`assets`,`item`])]),_:1}),m(P,{value:`refs`},{default:h(()=>[m(w,{item:a.item,type:`element`},null,8,[`item`])]),_:1}),(o(!0),s(t,null,c(a.subpanels,(e,t)=>(o(),n(P,{key:t,value:`ext-`+t},{default:h(()=>[(o(),n(f(e.component),{item:a.item,assets:a.assets},null,8,[`item`,`assets`]))]),_:2},1032,[`value`]))),128))]),_:1},8,[`modelValue`])]),_:1},512))]),_:1},8,[`aria-label`]),m(E,{item:a.item},null,8,[`item`]),(o(),n(g,{to:`body`},[a.vhistory?(o(),n(D,{key:0,modelValue:a.vhistory,"onUpdate:modelValue":_[4]||=e=>a.vhistory=e,readonly:!a.user.can(`element:save`),current:x.historyCurrent,load:()=>x.versions(a.item.id),onApply:x.apply,onUse:x.use},null,8,[`modelValue`,`readonly`,`current`,`load`,`onApply`,`onUse`])):l(``,!0),m(O,{modelValue:a.vchanged,"onUpdate:modelValue":_[5]||=e=>a.vchanged=e,changed:a.changed,targets:x.changeTargets,onResolve:_[6]||=e=>a.dirty=!0},null,8,[`modelValue`,`changed`,`targets`])]))],64)}var ee=k(Q,[[`render`,$]]);export{ee as default};