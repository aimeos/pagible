import{s as e}from"./graphql-B0HGj01_.js";import{r as t}from"./json-Dnt6XAOd.js";import{Lr as n,Pr as r,Ur as i,hr as a,kr as o}from"./index-Dsxw06Gt.js";import{toMp3 as s,transcription as c}from"./audio-BUaYAcEe.js";var l=e`
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
`;function f(e){let u=r(),d=o(),{$gettext:f}=i;return u.can(`audio:transcribe`)?s(a(e,!0)).then(e=>n.mutate({mutation:l,variables:{file:new File([e],`audio.mp3`,{type:`audio/mpeg`})},context:{hasUpload:!0}})).then(e=>c(t(e.data?.transcribe||`[]`,[]))).catch(e=>(d.error(f(`Error transcribing file`),e),c())):(d.denied(),Promise.resolve(c()))}function p(e,t,a=null,s=null){let c=r(),l=o(),{$gettext:d}=i;return c.can(`text:translate`)?(Array.isArray(e)||(e=[e].filter(e=>!!e)),e.length?t?n.mutate({mutation:u,variables:{texts:e,to:t.toUpperCase(),from:a?.toUpperCase(),context:s}}).then(e=>e.data?.translate||[]).catch(e=>{l.error(d(`Error translating texts`),e)}):Promise.reject(Error(`Target language is required`)):Promise.resolve([])):Promise.resolve(l.denied())}function m(e,t=[],a=[]){let s=r(),c=o(),{$gettext:l}=i;return s.can(`text:write`)?(e=String(e).trim(),e?(Array.isArray(t)||(t=[t]),t.push(`Only return the requested data without any additional information`),n.mutate({mutation:d,variables:{prompt:e,context:t.filter(e=>!!e).join(`
`),files:a.filter(e=>!!e)}}).then(e=>e.data?.write?.replace(/^"(.*)"$/,`$1`)||``).catch(e=>{c.error(l(`Error generating text`),e)})):(c.add(l(`Prompt is required for generating text`),`error`),Promise.resolve(``))):Promise.resolve(c.denied())}export{f as transcribe,p as translate,m as write};