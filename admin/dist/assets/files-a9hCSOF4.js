import{s as e}from"./graphql-B0HGj01_.js";import{i as t,r as n}from"./json-Dnt6XAOd.js";import{Pr as r,fr as i,zr as a}from"./index-Dsxw06Gt.js";var o=e`
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
`,s=e`
  ${o}
  mutation ($input: FileInput, $file: Upload, $disk: FileDisk) {
    addFile(input: $input, file: $file, disk: $disk) {
      ...CmsFileFields
    }
  }
`;async function c(e,t,n=null){let i=t.input?.lang?null:r().addLang(n);i&&(t={...t,input:{...t.input,lang:i}});let a={mutation:s,variables:t};return t.file&&(a.context={hasUpload:!0}),p((await e.mutate(a)).data?.addFile)}var l=e`
  mutation ($id: [ID!]!, $disk: FileDisk!) {
    relocateFile(id: $id, disk: $disk) {
      disk
      id
      editor
      updated_at
    }
  }
`,u=e`
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
`;async function d(e,t){let n=[];for(let r=0;r<t.length;r+=100){let i=t.slice(r,r+100);n.push(e.query({query:u,variables:{id:i,first:i.length},fetchPolicy:`no-cache`}))}return(await Promise.all(n)).flatMap(e=>e.data?.files?.data||[])}function f(e){let t={};for(let n of e)t[n.id]=p(n);return t}function p(e={}){let r=e=>typeof e==`string`?n(e):t(e||{}),i={...e,...r(e.latest?.data),...r(e.latest?.aux),disk:e.disk,id:e.id};for(let e of[`previews`,`description`,`transcription`])i[e]=Object.freeze(r(i[e]));return delete i.__typename,delete i.latest,i}function m(e){return i(e,Object.values(e.previews||{})[0]??e.path)}async function h(e,t,n){let r=await e.mutate({mutation:l,variables:{id:t,disk:n}});return a(`files`,e.getClient?.().cache??e.cache),r.data?.relocateFile||[]}function g(e){e?.path?.startsWith(`blob:`)&&URL.revokeObjectURL(e.path)}var _={mime:[`image/gif`,`image/jpeg`,`image/png`,`image/svg+xml`,`image/webp`]},v={mime:[`audio/mpeg`,`audio/mp4`,`audio/x-m4a`,`audio/aac`,`audio/ogg`,`audio/webm`,`audio/wav`,`audio/wave`,`audio/x-wav`,`audio/vnd.wave`,`audio/flac`,`audio/x-flac`]},y={mime:[`video/mp4`,`video/webm`,`video/ogg`]},b={mime:[..._.mime,...y.mime]};function x(e,...t){return new Promise((n,r)=>{e.toBlob(e=>e?n(e):r(Error(`Canvas can not be encoded`)),...t)})}function S(e,t=`image/png`){if(!e)return null;let n=atob(e),r=new Uint8Array(n.length);for(let e=0;e<n.length;e++)r[e]=n.charCodeAt(e);return new Blob([r],{type:t})}export{y as a,d as c,m as d,h as f,b as i,f as l,S as m,o as n,x as o,g as p,_ as r,c as s,v as t,p as u};