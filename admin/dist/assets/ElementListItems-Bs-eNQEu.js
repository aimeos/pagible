import{At as e,E as t,En as n,F as r,J as i,O as a,Qt as o,Un as s,Wt as c,X as l,Xt as u,Y as d,cr as f,dr as p,nt as m,q as h,rt as g,wn as _}from"./charts-qBJJ9lq9.js";import"./graphql-BUG_GYSd.js";import{s as v}from"./graphql-BRoDkLYC.js";import{S as y,x as b}from"./i18n-DRPnG9HE.js";import{t as x}from"./VBtn-CtGXdTrb.js";import{t as S}from"./VIcon-DDPYF8dY.js";import{M as C,c as w,f as T,n as E,o as D}from"./index-C2M3Uhl_.js";import{t as O}from"./ActionMenu-zrRDGydY.js";import{t as k}from"./ActionItem-LSwWNFgm.js";import{i as A,o as j,t as M}from"./lists-CHlWU-xa.js";import{t as N}from"./ListSort-CRAvh-K_.js";import{t as P}from"./SchemaDialog-CLHZ7Ya5.js";import{t as F}from"./EditBulkDialog-CiFPa2z8.js";import{t as I}from"./VCheckboxBtn-hjXhBJAJ.js";import{a as L,n as R}from"./files-BIiNSZI4.js";import{t as z}from"./VPagination-vxefMcwI.js";var B=v`
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
`,V=v`
  ${R}
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
`,H=Object.freeze([{column:`ID`,order:`DESC`,label:`Latest`},{column:`ID`,order:`ASC`,label:`Oldest`},{column:`LATEST_ID`,order:`DESC`,label:`Latest edit`},{column:`LATEST_ID`,order:`ASC`,label:`Oldest edit`},{column:`NAME`,order:`ASC`,label:`Name`},{column:`TYPE`,order:`ASC`,label:`Type`},{column:`EDITOR`,order:`ASC`,label:`Editor`}]),U={extends:M,components:{ActionItem:k,ActionMenu:O,ListStatus:j,SchemaDialog:P,EditBulkDialog:F,ListSort:N},data(){return{vschemas:!1}},setup(){return{...A(`element`,V,e=>e.vschemas=!0),sortOptions:H}},methods:{add(e){return this.embed||!this.user.can(`element:add`)?this.messages.denied():this.$apollo.mutate({mutation:B,variables:{input:{type:e.type,name:``,data:`{}`}}}).then(e=>{let t=e.data?.addElement||{};return t.data=b(t.data),t.published=!0,this.vschemas=!1,this.items.unshift(t),this.$emit(`select`,t),this.invalidate(),t}).catch(e=>{this.$log(`ElementListItems::add(): Error adding shared element`,e)})},failed(e){return{drop:this.$gettext(`Error trashing shared element`),keep:this.$gettext(`Error restoring shared element`),pub:this.$gettext(`Error publishing shared element`),purge:this.$gettext(`Error purging shared element`),save:this.$gettext(`Error saving shared element`),search:this.$gettext(`Error fetching shared elements`)}[e]},hydrate(e){let t=e.latest,n=t?.data?y(t.data):{...e,data:y(e.data)};return n.data&&typeof n.data==`object`&&(n.data=s(n.data)),Object.assign(n,{id:e.id,deleted_at:e.deleted_at,created_at:e.created_at,updated_at:e.latest?.created_at||e.updated_at,editor:e.latest?.editor||e.editor,published:e.latest?.published??!0,publish_at:e.latest?.publish_at||null,latest_id:e.latest?.id||null,files:Object.freeze((t?.files||e.files||[]).map(L))})}}},W={class:`header`},G={class:`bulk`},K={class:`btn-actions`},q={class:`search`},J={class:`layout`},Y={class:`actions`},X={class:`btn-actions`},Z=[`onClick`,`title`],Q={class:`item-text`},$={class:`item-head`},ee={key:0,class:`item-lang`},te={class:`item-title`},ne={class:`item-type item-subtitle`},re={class:`item-aux`},ie={class:`item-editor`},ae={class:`item-modified item-subtitle`},oe={key:1,class:`btn-group`};function se(s,v,y,b,C,O){let k=o(`ActionItem`),A=o(`ActionMenu`),j=o(`ListSort`),M=o(`ListStatus`),N=o(`SchemaDialog`),P=o(`EditBulkDialog`);return c(),l(r,null,[h(`div`,W,[h(`div`,G,[g(I,{"model-value":s.checked.size>0,onClick:v[0]||=a(e=>s.toggle(),[`stop`]),"aria-label":s.$gettext(`Toggle selection`)},null,8,[`model-value`,`aria-label`]),h(`span`,K,[g(A,null,{activator:_(({props:t,label:n})=>[g(x,e(t,{disabled:!s.isChecked||s.embed||!s.user.can(`element:add`),title:n,icon:s.mdiDotsVertical,variant:`text`}),null,16,[`disabled`,`title`,`icon`])]),default:_(()=>[n(g(k,{"prepend-icon":s.mdiPublish,onClick:v[1]||=e=>s.publish()},{default:_(()=>[m(p(s.$gettext(`Publish`))+` (`+p(s.counts.draft)+`)`,1)]),_:1},8,[`prepend-icon`]),[[t,s.counts.draft&&s.user.can(`element:publish`)]]),n(g(k,{"prepend-icon":s.mdiPencil,onClick:v[2]||=e=>s.edit()},{default:_(()=>[m(p(s.$gettext(`Edit properties`))+` (`+p(s.counts.all)+`) `,1)]),_:1},8,[`prepend-icon`]),[[t,s.isChecked&&s.user.can(`element:save`)]]),n(g(k,{"prepend-icon":s.mdiDelete,onClick:v[3]||=e=>s.drop()},{default:_(()=>[m(p(s.$gettext(`Delete`))+` (`+p(s.counts.live)+`) `,1)]),_:1},8,[`prepend-icon`]),[[t,s.counts.live&&s.user.can(`element:drop`)]]),n(g(k,{"prepend-icon":s.mdiDeleteRestore,onClick:v[4]||=e=>s.keep()},{default:_(()=>[m(p(s.$gettext(`Restore`))+` (`+p(s.counts.trashed)+`)`,1)]),_:1},8,[`prepend-icon`]),[[t,s.counts.trashed&&s.user.can(`element:keep`)]]),n(g(k,{"prepend-icon":s.mdiDeleteForever,onClick:v[5]||=e=>s.purge()},{default:_(()=>[m(p(s.$gettext(`Purge`))+` (`+p(s.counts.all)+`) `,1)]),_:1},8,[`prepend-icon`]),[[t,s.isChecked&&s.user.can(`element:purge`)]])]),_:1})]),!this.embed&&this.user.can(`element:add`)?(c(),i(x,{key:0,onClick:v[6]||=e=>C.vschemas=!0,title:s.$gettext(`Add element`),disabled:s.loading,icon:s.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])):d(``,!0)]),h(`div`,q,[g(T,{ref:`search`,modelValue:s.term,"onUpdate:modelValue":v[7]||=e=>s.term=e,"prepend-inner-icon":s.mdiMagnify,variant:`underlined`,label:s.$gettext(`Search for`),"hide-details":``,clearable:``},null,8,[`modelValue`,`prepend-inner-icon`,`label`])]),h(`div`,J,[s.outdated?(c(),i(x,{key:0,onClick:v[8]||=e=>s.reload(),"prepend-icon":s.mdiRefresh,title:s.$gettext(`Updated by another user`),color:`warning`,variant:`tonal`,size:`small`,rounded:`lg`,class:`btn-outdated`},{default:_(()=>[m(p(s.$gettext(`Refresh`)),1)]),_:1},8,[`prepend-icon`,`title`])):d(``,!0),g(x,{onClick:v[9]||=e=>s.reload(),loading:s.loading,title:s.$gettext(`Reload elements`),icon:s.mdiRefresh,class:`btn-reload`,variant:`text`},null,8,[`loading`,`title`,`icon`]),g(j,{modelValue:s.sort,"onUpdate:modelValue":v[10]||=e=>s.sort=e,options:b.sortOptions},null,8,[`modelValue`,`options`])])]),g(E,{class:`items`,onKeydown:s.listKey},{default:_(()=>[(c(!0),l(r,null,u(s.items,r=>(c(),i(w,{key:r.id,"data-id":r.id},{default:_(()=>[h(`div`,Y,[g(I,{"model-value":s.checked.has(r.id),"onUpdate:modelValue":e=>s.toggleCheck(r),class:f([{draft:!r.published},`item-check`])},null,8,[`model-value`,`onUpdate:modelValue`,`class`]),h(`span`,X,[g(A,null,{activator:_(({props:t,label:n})=>[g(x,e({ref_for:!0},t,{title:n,icon:s.mdiDotsVertical,variant:`text`}),null,16,[`title`,`icon`])]),default:_(()=>[n(g(k,{"prepend-icon":s.mdiPublish,onClick:e=>s.publish(r)},{default:_(()=>[m(p(s.$gettext(`Publish`)),1)]),_:1},8,[`prepend-icon`,`onClick`]),[[t,!r.deleted_at&&!r.published&&this.user.can(`element:publish`)]]),!r.deleted_at&&!r.published&&s.user.can(`element:publish`)&&s.user.can(`element:save`)?(c(),i(D,{key:0})):d(``,!0),s.user.can(`element:save`)?(c(),i(k,{key:1,"prepend-icon":s.mdiPencil,onClick:e=>s.edit(r)},{default:_(()=>[m(p(s.$gettext(`Edit properties`)),1)]),_:1},8,[`prepend-icon`,`onClick`])):d(``,!0),s.user.can(`element:save`)?(c(),i(D,{key:2})):d(``,!0),!r.deleted_at&&this.user.can(`element:drop`)?(c(),i(k,{key:3,"prepend-icon":s.mdiDelete,onClick:e=>s.drop(r)},{default:_(()=>[m(p(s.$gettext(`Delete`)),1)]),_:1},8,[`prepend-icon`,`onClick`])):d(``,!0),r.deleted_at&&this.user.can(`element:keep`)?(c(),i(k,{key:4,"prepend-icon":s.mdiDeleteRestore,onClick:e=>s.keep(r)},{default:_(()=>[m(p(s.$gettext(`Restore`)),1)]),_:1},8,[`prepend-icon`,`onClick`])):d(``,!0),this.user.can(`element:purge`)?(c(),i(k,{key:5,"prepend-icon":s.mdiDeleteForever,onClick:e=>s.purge(r)},{default:_(()=>[m(p(s.$gettext(`Purge`)),1)]),_:1},8,[`prepend-icon`,`onClick`])):d(``,!0)]),_:2},1024)])]),h(`a`,{href:`#`,class:f([`item-content`,{trashed:r.deleted_at}]),onClick:a(e=>s.$emit(`select`,r),[`prevent`]),title:s.title(r)},[h(`div`,Q,[h(`div`,$,[r.lang?(c(),l(`span`,ee,p(r.lang),1)):d(``,!0),r.publish_at?(c(),i(S,{key:1,class:`publish-at`,icon:s.mdiClockOutline},null,8,[`icon`])):d(``,!0),h(`span`,te,p(r.name||s.$gettext(`New`)),1)]),h(`div`,ne,p(r.type?.replace(`::`,` `)),1)]),h(`div`,re,[h(`div`,ie,p(r.editor),1),h(`div`,ae,p(new Date(r.updated_at).toLocaleString()),1)])],10,Z)]),_:2},1032,[`data-id`]))),128))]),_:1},8,[`onKeydown`]),g(M,{empty:!s.items?.length,filtered:s.filtered,loading:s.loading,resettable:!!(s.term||s.defaults),onReset:v[11]||=e=>s.resetFilter()},null,8,[`empty`,`filtered`,`loading`,`resettable`]),s.last>1?(c(),i(z,{key:0,modelValue:s.page,"onUpdate:modelValue":v[12]||=e=>s.page=e,length:s.last},null,8,[`modelValue`,`length`])):d(``,!0),!this.embed&&this.user.can(`element:add`)?(c(),l(`div`,oe,[g(x,{onClick:v[13]||=e=>C.vschemas=!0,title:s.$gettext(`Add element`),disabled:s.loading,icon:s.mdiPlus,class:`btn-add`,color:`primary`,variant:`tonal`},null,8,[`title`,`disabled`,`icon`])])):d(``,!0),g(N,{modelValue:C.vschemas,"onUpdate:modelValue":v[14]||=e=>C.vschemas=e,elements:!1,onAdd:v[15]||=e=>O.add(e)},null,8,[`modelValue`]),g(P,{modelValue:s.editDialog,"onUpdate:modelValue":v[16]||=e=>s.editDialog=e,count:s.editIds.length,onApply:s.save},null,8,[`modelValue`,`count`,`onApply`])],64)}var ce=C(U,[[`render`,se],[`__scopeId`,`data-v-2afb2bf4`]]);export{ce as default};