import{s as e}from"./graphql-BP2ngTk6.js";import{dr as t,fr as n}from"./index-C8Y0m_Fc.js";var r=e`
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
`,i=e`
  ${r}
  mutation ($input: FileInput, $file: Upload, $disk: FileDisk) {
    addFile(input: $input, file: $file, disk: $disk) {
      ...CmsFileFields
    }
  }
`;async function a(e,t){let n={mutation:i,variables:t};t.file&&(n.context={hasUpload:!0});let r=await e.mutate(n);if(r.errors)throw r.errors;return l(r.data?.addFile)}var o=e`
  mutation ($id: [ID!]!, $disk: FileDisk!) {
    relocateFile(id: $id, disk: $disk) {
      disk
      id
      editor
      updated_at
    }
  }
`,s=e`
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
`;function c(e){let t={};for(let n of e)t[n.id]=l(n);return t}function l(e={}){let r=e=>typeof e==`string`?t(e):n(e||{}),i={...e,...r(e.latest?.data),...r(e.latest?.aux),disk:e.disk,id:e.id};for(let e of[`previews`,`description`,`transcription`])i[e]=Object.freeze(r(i[e]));return delete i.__typename,delete i.latest,i}export{c as a,a as i,r as n,l as o,o as r,s as t};