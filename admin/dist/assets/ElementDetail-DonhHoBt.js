import{At as e,F as t,J as n,O as r,Qt as i,Un as a,Wt as o,X as s,Xt as c,cr as l,dr as u,en as d,ht as f,lr as p,nt as m,rt as h,wn as g}from"./tree-dVfVAcku.js";import{s as _}from"./graphql-B0HGj01_.js";import{n as v,r as y}from"./json-Dnt6XAOd.js";import{t as b}from"./VSelect-rGtv6eLt.js";import{i as x}from"./loader-B_MADvU8.js";import{t as S}from"./VContainer-CxWG7FG-.js";import{n as C,t as w}from"./VRow-DB5JnQdC.js";import{Dr as T,Pr as E,m as D,mi as O,ot as k,si as A}from"./index-Dsxw06Gt.js";import{o as j}from"./history-BsugKXeC.js";import{a as M,o as N,r as P,t as F}from"./VTabs-DGVTf1Jn.js";import{r as I}from"./options-BzSiEpYn.js";import{t as L}from"./VSheet-CPspKhlz.js";import{l as R,n as z}from"./files-a9hCSOF4.js";import{i as B}from"./field-DA6aXPw5.js";import{i as V,r as H,t as U}from"./detail-CiEVWiPv.js";import{t as W}from"./Fields-x3_5FMOf.js";import{t as G}from"./VMain-CCRVWXVd.js";import{t as K}from"./VForm-CySInwqn.js";import{t as q}from"./DetailRefs-SCzpKc_A.js";var J={components:{Fields:W},props:{item:{type:Object,required:!0},assets:{type:Object,default:()=>({})}},emits:[`patch`,`add-assets`,`error`],setup(){let e=T();return{user:E(),schemas:e,localeItems:I}},computed:{readonly(){return!this.user.canLang(`element:save`,this.item.lang)},langItems(){return I(!0).filter(e=>this.user.canLang(`element:save`,e.value))}},methods:{fields(e){return B(this,this.schemas,`content`,e)},update(e,t){this.$emit(`patch`,{[e]:t})}}};function Y(e,t,r,a,s,c){let l=i(`Fields`);return o(),n(S,null,{default:g(()=>[h(L,{class:`scroll`},{default:g(()=>[h(w,null,{default:g(()=>[h(C,{cols:`12`,md:`6`},{default:g(()=>[h(D,{ref:`name`,readonly:c.readonly,modelValue:r.item.name,"onUpdate:modelValue":t[0]||=e=>c.update(`name`,e),variant:`underlined`,label:e.$gettext(`Name`)+` ‒ `+e.$gettext(`Name to find the shared element in the element list`),counter:`255`,maxlength:`255`},null,8,[`readonly`,`modelValue`,`label`])]),_:1}),h(C,{cols:`12`,md:`6`},{default:g(()=>[h(b,{ref:`lang`,items:c.langItems,readonly:c.readonly,modelValue:r.item.lang,"onUpdate:modelValue":t[1]||=e=>c.update(`lang`,e),variant:`underlined`,label:e.$gettext(`Language`)+` ‒ `+e.$gettext(`Language of the element content, choose none if used in all languages`)},null,8,[`items`,`readonly`,`modelValue`,`label`])]),_:1})]),_:1}),h(w,null,{default:g(()=>[h(C,{cols:`12`},{default:g(()=>[h(l,{ref:`field`,data:r.item.data,files:r.item.files,fields:c.fields(r.item.type),readonly:c.readonly,assets:r.assets,type:r.item.type,"onUpdate:data":t[2]||=e=>c.update(`data`,e),"onUpdate:files":t[3]||=e=>c.update(`files`,e),onAddAssets:t[4]||=t=>e.$emit(`add-assets`,t),onError:t[5]||=t=>e.$emit(`error`,t)},null,8,[`data`,`files`,`fields`,`readonly`,`assets`,`type`])]),_:1})]),_:1})]),_:1})]),_:1})}var X=k(J,[[`render`,Y],[`__scopeId`,`data-v-d0d30c7b`]]),Z=_`
  ${z}
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
`,Q=_`
  mutation ($id: ID!, $input: ElementInput!, $latestId: ID) {
    saveElement(id: $id, input: $input, latestId: $latestId) {
      id
      latest { id published publish_at editor created_at }
      changed
    }
  }
`,$=_`
  ${z}
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
`,ee={extends:U,components:{...U.components,DetailRefs:q,ElementDetailItem:X},data:()=>({assets:{}}),setup(){return{...H(`element`),schemas:T()}},created(){this.schemas.load()},computed:{changeTargets(){return a({data:this.item})}},methods:{historyCurrent(){let e=this.item,t=new Set(e.files||[]),n=Object.fromEntries(Object.entries(this.assets).filter(([e])=>t.has(e)));return a({data:Object.freeze({data:A(e.data||{}),scheduled:+!!e.publish_at,lang:e.lang,type:e.type,name:e.name}),files:a(n)})},addAssets(e){this.assets=a({...this.assets,...e})},reload(){return this.reloadVersion(Z,this.$gettext(`Error fetching element`),e=>{Object.assign(this.item,y(e.latest?.data)),this.item.latestId=e.latest?.id;let t=e.latest?.files||e.files||[];this.assets=a(R(t)),this.item.files=t.map(e=>e.id)},()=>!this.dirty)},apply(e,t){t&&(this.assets=a({...t.files,...this.assets})),Object.assign(this.item,e),`data`in e&&(this.item.files=j(this.item.data)),this.markChanged(),this.vhistory=!1},files:R,saveItem(e){return this.saveWith(()=>(this.item.name||(this.item.name=O(this.item.data)),{mutation:Q,variables:{id:this.item.id,input:{type:this.item.type,name:this.item.name,lang:this.item.lang,data:JSON.stringify(this.item.data||{})},latestId:this.item.latestId}}),(t,n,r)=>{let i=t.data?.saveElement,o=i?.changed?a(y(i.changed)):null;(o?.latest?.id||i?.latest?.id)&&(this.item.latestId=o?.latest?.id??i.latest.id),V(this,o,this.$gettext(`Element saved successfully`),e,n,r),this.saved(i?.latest)},this.$gettext(`Error saving element`))},use(e,t=!1){Object.assign(this.item,e.data),this.assets=a({...e.files||{}}),this.item.files=Object.keys(e.files||{}),this.vhistory=!1,this.markChanged(),t&&this.reset()},versions(e){return this.loadVersions($,e,e=>Object.freeze({...e,data:v(e.data),files:Object.freeze(this.files(e.files||[]))}))}}};function te(a,_,v,y,b,S){let C=i(`DetailAppBar`),w=i(`ElementDetailItem`),T=i(`DetailRefs`),E=i(`AsideMeta`),D=i(`DetailDialogs`);return o(),s(t,null,[h(C,e(a.bar,{label:a.$gettext(`Element`),"has-latest":!!a.item.latestId}),null,16,[`label`,`has-latest`]),h(G,{class:`element-details`,"aria-label":a.$gettext(`Element`)},{default:g(()=>[a.loading?(o(),n(x,{key:0,indeterminate:``,color:`primary`})):(o(),n(K,{key:1,ref:`form`,onSubmit:_[3]||=r(()=>{},[`prevent`])},{default:g(()=>[h(F,{class:`detail-tabs`,"fixed-tabs":``,"hide-slider":``,modelValue:a.tab,"onUpdate:modelValue":_[0]||=e=>a.tab=e},{default:g(()=>[h(N,{value:`element`,class:l({changed:a.dirty,error:a.error})},{default:g(()=>[m(u(a.$gettext(`Element`)),1)]),_:1},8,[`class`]),h(N,{value:`refs`},{default:g(()=>[m(u(a.$gettext(`Used by`)),1)]),_:1}),(o(!0),s(t,null,c(a.subpanels,(e,t)=>(o(),n(N,{key:t,value:`ext-`+t},{default:g(()=>[m(u(a.label(e)),1)]),_:2},1032,[`value`]))),128))]),_:1},8,[`modelValue`]),h(M,{modelValue:a.tab,"onUpdate:modelValue":_[2]||=e=>a.tab=e,touch:!1},{default:g(()=>[h(P,{value:`element`},{default:g(()=>[h(w,{onPatch:a.itemUpdated,onAddAssets:S.addAssets,onError:_[1]||=e=>a.error=e,assets:a.assets,item:a.item},null,8,[`onPatch`,`onAddAssets`,`assets`,`item`])]),_:1}),h(P,{value:`refs`},{default:g(()=>[h(T,{item:a.item,type:`element`},null,8,[`item`])]),_:1}),(o(!0),s(t,null,c(a.subpanels,(e,t)=>(o(),n(P,{key:t,value:`ext-`+t},{default:g(()=>[(o(),n(d(e.component),{item:a.item,assets:a.assets},null,8,[`item`,`assets`]))]),_:2},1032,[`value`]))),128))]),_:1},8,[`modelValue`])]),_:1},512))]),_:1},8,[`aria-label`]),h(E,{item:a.item},null,8,[`item`]),h(D,p(f(a.dialogs)),null,16)],64)}var ne=k(ee,[[`render`,te]]);export{ne as default};