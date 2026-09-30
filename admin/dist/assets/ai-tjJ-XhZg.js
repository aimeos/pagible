import{s as e}from"./graphql-BP2ngTk6.js";import{Kr as t,ei as n,fr as r,gr as i,qr as a,tr as o}from"./index-nCgNV5DG.js";import{toMp3 as s,transcription as c}from"./audio-BiycTRR8.js";var l=e`
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
`;function f(e){let u=i(),d=r(),{$gettext:f}=a;return u.can(`audio:transcribe`)?s(t(e,!0)).then(e=>o.mutate({mutation:l,variables:{file:new File([e],`audio.mp3`,{type:`audio/mpeg`})},context:{hasUpload:!0}})).then(e=>{if(e.errors)throw e;return c(n(e.data?.transcribe||`[]`,[]))}).catch(e=>(d.add(f(`Error transcribing file`)+`:
`+e,`error`),console.error(`useAi::transcribe(): Error transcribing from media URL`,e),c())):(d.add(f(`Permission denied`),`error`),Promise.resolve(c()))}function p(e,t,n=null,s=null){let c=i(),l=r(),{$gettext:d}=a;if(!c.can(`text:translate`)){l.add(d(`Permission denied`),`error`);return}return Array.isArray(e)||(e=[e].filter(e=>!!e)),e.length?t?o.mutate({mutation:u,variables:{texts:e,to:t.toUpperCase(),from:n?.toUpperCase(),context:s}}).then(e=>{if(e.errors)throw e;return e.data?.translate||[]}).catch(e=>{l.add(d(`Error translating texts`)+`:
`+e,`error`),console.error(`useAi::translate(): Error translating texts`,e)}):Promise.reject(Error(`Target language is required`)):Promise.resolve([])}function m(e,t=[],n=[]){let s=i(),c=r(),{$gettext:l}=a;if(!s.can(`text:write`)){c.add(l(`Permission denied`),`error`);return}return e=String(e).trim(),e?(Array.isArray(t)||(t=[t]),t.push(`Only return the requested data without any additional information`),o.mutate({mutation:d,variables:{prompt:e,context:t.filter(e=>!!e).join(`
`),files:n.filter(e=>!!e)}}).then(e=>{if(e.errors)throw e;return e.data?.write?.replace(/^"(.*)"$/,`$1`)||``}).catch(e=>{c.add(l(`Error generating text`)+`:
`+e,`error`),console.error(`useAi::write(): Error generating text`,e)})):(c.add(l(`Prompt is required for generating text`),`error`),Promise.resolve(``))}export{f as transcribe,p as translate,m as write};