import{s as e}from"./graphql-REqfkFwC.js";import{Xn as t,Yn as n}from"./index-B9Hpcyg2.js";var r=e`
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
`,a=e`
  mutation ($id: [ID!]!, $disk: FileDisk!) {
    relocateFile(id: $id, disk: $disk) {
      disk
      id
      editor
      updated_at
    }
  }
`,o=e`
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
`;function s(e={}){let r=e=>typeof e==`string`?n(e):t(e||{}),i={...e,...r(e.latest?.data),...r(e.latest?.aux),disk:e.disk,id:e.id};for(let e of[`previews`,`description`,`transcription`])i[e]=Object.freeze(r(i[e]));return delete i.__typename,delete i.latest,i}export{s as a,a as i,o as n,r,i as t};