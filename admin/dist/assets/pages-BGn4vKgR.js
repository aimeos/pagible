import{s as e}from"./graphql-BRoDkLYC.js";import{r as t}from"./json-Dnt6XAOd.js";var n=`AI draft`,r=1e3,i=new Set(`access.cache.created_at.deleted_at.domain.editor.has.id.lang.latest_id.name.parent_id.path.publish_at.published.restricted.source.stale.status.tag.theme.title.to.type.updated_at.variant_deleted_at`.split(`.`));function a(e,t){for(let n in t)(n in e||i.has(n))&&(e[n]=t[n])}var o=e`
  mutation ($ids: [ID!]!) {
    clearCache(ids: $ids)
  }
`,s=e`
  mutation ($input: PageInput!, $parent: ID, $ref: ID) {
    addPage(input: $input, parent: $parent, ref: $ref) {
      id
    }
  }
`,c=e`
  mutation ($id: ID!, $parent: ID, $ref: ID) {
    movePage(id: $id, parent: $parent, ref: $ref) {
      id
    }
  }
`,l=e`
  mutation ($id: [ID!]!, $input: PageInput!, $descendants: Boolean, $lang: String) {
    bulkPage(id: $id, input: $input, descendants: $descendants, lang: $lang) {
      ids
      latest
      data
      failed
    }
  }
`,u=`id
          access @include(if: $access)
          parent_id
          lang
          source
          stale
          created_at
          deleted_at
          variant_deleted_at
          editor
          has
          restricted
          latest {
            id
            published
            publish_at
            data
            editor
            created_at
          }`,d=e`
  mutation ($id: ID!, $lang: String!, $access: Boolean!) {
    addPageVariant(id: $id, lang: $lang) {
      ${u}
    }
  }
`,f=e`
  mutation ($id: ID!, $parent: ID, $ref: ID) {
    copyPage(id: $id, parent: $parent, ref: $ref) {
      id
    }
  }
`,p=e`
  query(
    $filter: PageFilter,
    $sort: [QueryPagesSortOrderByClause!],
    $limit: Int!,
    $page: Int!,
    $trashed: Trashed,
    $publish: Publish,
    $lang: String,
    $access: Boolean!
  ) {
    pages(
      filter: $filter,
      sort: $sort,
      first: $limit,
      page: $page,
      trashed: $trashed,
      publish: $publish,
      lang: $lang
    ) {
      data {
        ${u}
        variants(lang: $lang) {
          state
        }
      }
      paginatorInfo {
        currentPage
        lastPage
      }
    }
  }
`;function m(e){let t=e.latest;return{updated_at:t?.created_at||e.updated_at,editor:t?.editor||e.editor,published:t?.published??!0,publish_at:t?.publish_at||null,latest_id:t?.id||null}}function h(e){let n=e.latest?.data?t(e.latest.data):{...e};return Object.assign(n,{access:e.access,id:e.id,has:e.has,restricted:e.restricted,parent_id:e.parent_id,deleted_at:e.deleted_at,created_at:e.created_at,...m(e),lang:e.lang??n.lang??null,source:e.source??null,stale:!!e.stale,variant_deleted_at:e.variant_deleted_at||null,variant_trashed:e.variants?.[0]?.state===`trashed`})}export{p as a,r as c,a as d,m as f,f as i,l,n,s as o,o as r,c as s,d as t,h as u};