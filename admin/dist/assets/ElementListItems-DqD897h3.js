import{F as e,J as t,O as n,Qt as r,Un as i,Wt as a,X as o,Xt as s,Y as c,cr as l,dr as u,q as d,rt as f,wn as p}from"./charts-qBJJ9lq9.js";import{s as m}from"./utils-Cf4ICb5e.js";import{s as h}from"./graphql-BRoDkLYC.js";import{n as g,r as _}from"./json-Dnt6XAOd.js";import{t as v}from"./VBtn-CtGXdTrb.js";import{t as y}from"./VIcon-DDPYF8dY.js";import{h as b,l as x,z as S}from"./index-CfOwHhUR.js";import{n as C,r as w,t as T}from"./EditBulkDialog-0xV0Tu2w.js";import{n as E,r as D,t as O}from"./ListSort-BMUUVVnb.js";import{t as k}from"./ListStatus-DC9vlvCo.js";import{t as A}from"./SchemaDialog-7PxZ7pUU.js";import{t as j}from"./VCheckboxBtn-hjXhBJAJ.js";import{n as M,u as N}from"./files-LNke4jow.js";import{a as P,t as F}from"./lists-DUAOQFhP.js";import{f as I}from"./pages-BGn4vKgR.js";import{t as L}from"./VPagination-vxefMcwI.js";var R=h`
  mutation ($input: ElementInput!) {
    addElement(input: $input) {
      id
      lang
      name
      type
      data
      editor
      created_at
      updated_at
      deleted_at
    }
  }
`,z=h`
  ${M}
  query (
    $filter: ElementFilter
    $sort: [QueryElementsSortOrderByClause!]
    $limit: Int!
    $page: Int!
    $trashed: Trashed
    $publish: Publish
  ) {
    elements(
      filter: $filter
      sort: $sort
      first: $limit
      page: $page
      trashed: $trashed
      publish: $publish
    ) {
      data {
        id
        lang
        name
        type
        data
        editor
        created_at
        updated_at
        deleted_at
        files {
          ...CmsFileFields
        }
        latest {
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
      paginatorInfo {
        lastPage
      }
    }
  }
`,B=Object.freeze([{column:`ID`,order:`DESC`,label:`Latest`},{column:`ID`,order:`ASC`,label:`Oldest`},{column:`LATEST_ID`,order:`DESC`,label:`Latest edit`},{column:`LATEST_ID`,order:`ASC`,label:`Oldest edit`},{column:`NAME`,order:`ASC`,label:`Name`},{column:`TYPE`,order:`ASC`,label:`Type`},{column:`EDITOR`,order:`ASC`,label:`Editor`}]),V={extends:F,components:{ListBulkMenu:w,ListItemMenu:C,ListReload:D,ListSearch:E,ListStatus:k,SchemaDialog:A,EditBulkDialog:T,ListSort:O},data(){return{vschemas:!1}},setup(){return{...P(`element`,z,e=>e.vschemas=!0),formatDate:m,sortOptions:B}},methods:{add(e){return this.embed||!this.user.can(`element:add`)?this.messages.denied():this.$apollo.mutate({mutation:R,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{let t=e.data?.addElement||{};return t.data=g(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(t=>{this.messages.error(this.$gettext(`Error adding shared element`),t,e)})},failed(e){return{drop:this.$gettext(`Error trashing shared element`),keep:this.$gettext(`Error restoring shared element`),pub:this.$gettext(`Error publishing shared element`),purge:this.$gettext(`Error purging shared element`),save:this.$gettext(`Error saving shared element`),search:this.$gettext(`Error fetching shared elements`)}[e]},hydrate(e){let t=e.latest,n=t?.data?_(t.data):{...e,data:_(e.data)};return n.data&&typeof n.data==`object`&&(n.data=i(n.data)),Object.assign(n,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,...I(e),files:Object.freeze((t?.files||e.files||[]).map(N))})}}},H={class:`header`},U={class:`bulk`},W={class:`layout`},G={class:`actions`},K={class:`btn-actions`},q=[`onClick`,`title`],J={class:`item-text`},Y={class:`item-head`},X={key:0,class:`item-lang`},Z={class:`item-title`},Q={class:`item-type item-subtitle`},$={class:`item-aux`},ee={class:`item-editor`},te={class:`item-modified item-subtitle`},ne={key:1,class:`btn-group`};function re(i,m,h,g,_,S){let C=r(`ListBulkMenu`),w=r(`ListSearch`),T=r(`ListReload`),E=r(`ListSort`),D=r(`ListItemMenu`),O=r(`ListStatus`),k=r(`SchemaDialog`),A=r(`EditBulkDialog`);return a(),o(e,null,[d(`div`,H,[d(`div`,U,[f(j,{"model-value":i.allChecked,indeterminate:i.isChecked&&!i.allChecked,onClick:m[0]||=n(e=>i.toggle(),[`stop`]),"aria-label":i.$gettext(`Toggle selection`)},null,8,[`model-value`,`indeterminate`,`aria-label`]),f(C,{type:`element`,counts:i.counts,selected:i.isChecked,disabled:!i.isChecked||i.embed,onPublish:m[1]||=e=>i.publish(),onEdit:m[2]||=e=>i.edit(),onDrop:m[3]||=e=>i.drop(),onKeep:m[4]||=e=>i.keep(),onPurge:m[5]||=e=>i.purge()},null,8,[`counts`,`selected`,`disabled`]),!i.embed&&i.user.can(`element:add`)?(a(),t(v,{key:0,onClick:m[6]||=e=>_.vschemas=!0,title:i.$gettext(`Add element`),disabled:i.loading,icon:i.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):c(``,!0)]),f(w,{ref:`search`,modelValue:i.term,"onUpdate:modelValue":m[7]||=e=>i.term=e},null,8,[`modelValue`]),d(`div`,W,[f(T,{loading:i.loading,outdated:i.outdated,title:i.$gettext(`Reload elements`),onReload:m[8]||=e=>i.reload()},null,8,[`loading`,`outdated`,`title`]),f(E,{modelValue:i.sort,"onUpdate:modelValue":m[9]||=e=>i.sort=e,options:g.sortOptions},null,8,[`modelValue`,`options`])])]),f(x,{class:`items`,onKeydown:i.listKey},{default:p(()=>[(a(!0),o(e,null,s(i.items,e=>(a(),t(b,{key:e.id,"data-id":e.id},{default:p(()=>[d(`div`,G,[f(j,{"model-value":i.checked.has(e.id),"onUpdate:modelValue":t=>i.toggleCheck(e),"aria-label":i.$gettext(`Toggle selection`),class:l([{draft:!e.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`aria-label`,`class`]),d(`span`,K,[f(D,{type:`element`,item:e,onPublish:t=>i.publish(e),onEdit:t=>i.edit(e),onDrop:t=>i.drop(e),onKeep:t=>i.keep(e),onPurge:t=>i.purge(e)},null,8,[`item`,`onPublish`,`onEdit`,`onDrop`,`onKeep`,`onPurge`])])]),d(`a`,{href:`#`,class:l([`item-content`,{trashed:e.deleted_at}]),onClick:n(t=>i.$emit(`select`,e),[`prevent`]),title:i.title(e)},[d(`div`,J,[d(`div`,Y,[e.lang?(a(),o(`span`,X,u(e.lang),1)):c(``,!0),e.publish_at?(a(),t(y,{key:1,class:`publish-at`,icon:i.mdiClockOutline},null,8,[`icon`])):c(``,!0),d(`span`,Z,u(e.name||i.$gettext(`New`)),1)]),d(`div`,Q,u(e.type?.replace(`::`,` `)),1)]),d(`div`,$,[d(`div`,ee,u(e.editor),1),d(`div`,te,u(g.formatDate(e.updated_at,i.$vuetify.locale.current)),1)])],10,q)]),_:2},1032,[`data-id`]))),128))]),_:1},8,[`onKeydown`]),f(O,{empty:!i.items?.length,filtered:i.filtered,loading:i.loading,resettable:!!(i.term||i.defaults),onReset:m[10]||=e=>i.resetFilter()},null,8,[`empty`,`filtered`,`loading`,`resettable`]),i.last>1?(a(),t(L,{key:0,modelValue:i.page,"onUpdate:modelValue":m[11]||=e=>i.page=e,length:i.last},null,8,[`modelValue`,`length`])):c(``,!0),!i.embed&&i.user.can(`element:add`)?(a(),o(`div`,ne,[f(v,{onClick:m[12]||=e=>_.vschemas=!0,title:i.$gettext(`Add element`),disabled:i.loading,icon:i.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):c(``,!0),f(k,{modelValue:_.vschemas,"onUpdate:modelValue":m[13]||=e=>_.vschemas=e,elements:!1,onAdd:m[14]||=e=>S.add(e)},null,8,[`modelValue`]),f(A,{modelValue:i.editDialog,"onUpdate:modelValue":m[15]||=e=>i.editDialog=e,count:i.editIds.length,onApply:i.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var ie=S(V,[[`render`,re],[`__scopeId`,`data-v-12955c84`]]);export{ie as default};