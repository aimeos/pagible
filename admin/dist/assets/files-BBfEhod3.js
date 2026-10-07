import{F as e,r as t}from"./graphql-CZAUv3_A.js";import{s as n}from"./graphql-BRoDkLYC.js";import{C as r,S as i}from"./i18n-DRPnG9HE.js";var a=n`
  fragment CmsFileFields on File {
    disk
    id
    lang
    mime
    name
    path
    previews
    description
    transcription
    editor
    created_at
    updated_at
    deleted_at
    latest {
      data
      aux
    }
  }
`,o=n`
  ${a}
  mutation ($input: FileInput, $file: Upload, $disk: FileDisk) {
    addFile(input: $input, file: $file, disk: $disk) {
      ...CmsFileFields
    }
  }
`;async function s(e,t){let n={mutation:o,variables:t};return t.file&&(n.context={hasUpload:!0}),d((await e.mutate(n)).data?.addFile)}var c=n`
  mutation ($id: [ID!]!, $disk: FileDisk!) {
    relocateFile(id: $id, disk: $disk) {
      disk
      id
      editor
      updated_at
    }
  }
`,l=n`
  query ($id: [ID!]!) {
    files(filter: { id: $id }, first: 100) {
      data {
        disk
        id
        editor
        updated_at
      }
    }
  }
`;function u(e){let t={};for(let n of e)t[n.id]=d(n);return t}function d(e={}){let t=e=>typeof e==`string`?i(e):r(e||{}),n={...e,...t(e.latest?.data),...t(e.latest?.aux),disk:e.disk,id:e.id};for(let e of[`previews`,`description`,`transcription`])n[e]=Object.freeze(t(n[e]));return delete n.__typename,delete n.latest,n}function f(t){return e(t,Object.values(t.previews||{})[0]??t.path)}async function p(e,n,r){let i=await e.mutate({mutation:c,variables:{id:n,disk:r}});return t(`files`),i.data?.relocateFile||[]}function m(e){e?.path?.startsWith(`blob:`)&&URL.revokeObjectURL(e.path)}export{d as a,m as c,u as i,a as n,f as o,s as r,p as s,l as t};