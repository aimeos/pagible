import{s as e}from"./graphql-REqfkFwC.js";import{Cn as t,Dn as n,Jn as r,hn as i,nr as a,tr as o}from"./index-DBWL-LJ8.js";import{toMp3 as s,transcription as c}from"./audio-M0-QB1fe.js";var l=e`
  mutation ($file: Upload!) {
    transcribe(file: $file)
  }
`,u=e`
  mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
    translate(texts: $texts, to: $to, from: $from, context: $context)
  }
`,d=e`
  mutation ($prompt: String!, $context: String, $files: [String!]) {
    write(prompt: $prompt, context: $context, files: $files)
  }
`;function f(e){let u=n(),d=t(),{$gettext:f}=a;return u.can(`audio:transcribe`)?s(o(e,!0)).then(e=>i.mutate({mutation:l,variables:{file:new File([e],`audio.mp3`,{type:`audio/mpeg`})},context:{hasUpload:!0}})).then(e=>{if(e.errors)throw e;return c(r(e.data?.transcribe||`[]`,[]))}).catch(e=>(d.add(f(`Error transcribing file`)+`:
`+e,`error`),console.error(`useAi::transcribe(): Error transcribing from media URL`,e),c())):(d.add(f(`Permission denied`),`error`),Promise.resolve(c()))}function p(e,r,o=null,s=null){let c=n(),l=t(),{$gettext:d}=a;if(!c.can(`text:translate`)){l.add(d(`Permission denied`),`error`);return}return Array.isArray(e)||(e=[e].filter(e=>!!e)),e.length?r?i.mutate({mutation:u,variables:{texts:e,to:r.toUpperCase(),from:o?.toUpperCase(),context:s}}).then(e=>{if(e.errors)throw e;return e.data?.translate||[]}).catch(e=>{l.add(d(`Error translating texts`)+`:
`+e,`error`),console.error(`useAi::translate(): Error translating texts`,e)}):Promise.reject(Error(`Target language is required`)):Promise.resolve([])}function m(e,r=[],o=[]){let s=n(),c=t(),{$gettext:l}=a;if(!s.can(`text:write`)){c.add(l(`Permission denied`),`error`);return}return e=String(e).trim(),e?(Array.isArray(r)||(r=[r]),r.push(`Only return the requested data without any additional information`),i.mutate({mutation:d,variables:{prompt:e,context:r.filter(e=>!!e).join(`
`),files:o.filter(e=>!!e)}}).then(e=>{if(e.errors)throw e;return e.data?.write?.replace(/^"(.*)"$/,`$1`)||``}).catch(e=>{c.add(l(`Error generating text`)+`:
`+e,`error`),console.error(`useAi::write(): Error generating text`,e)})):(c.add(l(`Prompt is required for generating text`),`error`),Promise.resolve(``))}export{f as transcribe,p as translate,m as write};