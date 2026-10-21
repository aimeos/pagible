import{C as e}from"./stores-Bm6PX1aQ.js";import{s as t}from"./graphql-BRoDkLYC.js";import{i as n,r}from"./json-Dnt6XAOd.js";import{a as i}from"./index-CfOwHhUR.js";var a=t`
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
`,o=t`
  ${a}
  mutation ($input: FileInput, $file: Upload, $disk: FileDisk) {
    addFile(input: $input, file: $file, disk: $disk) {
      ...CmsFileFields
    }
  }
`;async function s(e,t){let n={mutation:o,variables:t};return t.file&&(n.context={hasUpload:!0}),f((await e.mutate(n)).data?.addFile)}var c=t`
  mutation ($id: [ID!]!, $disk: FileDisk!) {
    relocateFile(id: $id, disk: $disk) {
      disk
      id
      editor
      updated_at
    }
  }
`,l=t`
  query ($id: [ID!]!, $first: Int!) {
    files(filter: { id: $id }, first: $first) {
      data {
        disk
        id
        editor
        updated_at
      }
    }
  }
`;async function u(e,t){let n=[];for(let r=0;r<t.length;r+=100){let i=t.slice(r,r+100);n.push(e.query({query:l,variables:{id:i,first:i.length},fetchPolicy:`no-cache`}))}return(await Promise.all(n)).flatMap(e=>e.data?.files?.data||[])}function d(e){let t={};for(let n of e)t[n.id]=f(n);return t}function f(e={}){let t=e=>typeof e==`string`?r(e):n(e||{}),i={...e,...t(e.latest?.data),...t(e.latest?.aux),disk:e.disk,id:e.id};for(let e of[`previews`,`description`,`transcription`])i[e]=Object.freeze(t(i[e]));return delete i.__typename,delete i.latest,i}function p(e){return i(e,Object.values(e.previews||{})[0]??e.path)}async function m(t,n,r){let i=await t.mutate({mutation:c,variables:{id:n,disk:r}});return e(`files`,t.getClient?.().cache??t.cache),i.data?.relocateFile||[]}function h(e){e?.path?.startsWith(`blob:`)&&URL.revokeObjectURL(e.path)}var g={mime:[`image/gif`,`image/jpeg`,`image/png`,`image/svg+xml`,`image/webp`]},_={mime:[`audio/mpeg`,`audio/mp4`,`audio/x-m4a`,`audio/aac`,`audio/ogg`,`audio/webm`,`audio/wav`,`audio/wave`,`audio/x-wav`,`audio/vnd.wave`,`audio/flac`,`audio/x-flac`]},v={mime:[`video/mp4`,`video/webm`,`video/ogg`]},y={mime:[...g.mime,...v.mime]};function b(e,...t){return new Promise((n,r)=>{e.toBlob(e=>e?n(e):r(Error(`Canvas can not be encoded`)),...t)})}function x(e,t=`image/png`){if(!e)return null;let n=atob(e),r=new Uint8Array(n.length);for(let e=0;e<n.length;e++)r[e]=n.charCodeAt(e);return new Blob([r],{type:t})}export{v as a,u as c,p as d,m as f,y as i,d as l,x as m,a as n,b as o,h as p,g as r,s,_ as t,f as u};