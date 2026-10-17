import{m as e,n as t,tt as n,y as r}from"./graphql-Kpn7PV2y.js";import{s as i}from"./graphql-BRoDkLYC.js";import{S as a,t as o}from"./i18n-HewPHv_e.js";import{toMp3 as s,transcription as c}from"./audio-DaRI_C0K.js";var l=i`
  mutation ($file: Upload!) {
    transcribe(file: $file)
  }
`,u=i`
  mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
    translate(texts: $texts, to: $to, from: $from, context: $context)
  }
`,d=i`
  mutation ($prompt: String!, $context: String, $files: [String!]) {
    write(prompt: $prompt, context: $context, files: $files)
  }
`;function f(i){let u=r(),d=e(),{$gettext:f}=o;return u.can(`audio:transcribe`)?s(n(i,!0)).then(e=>t.mutate({mutation:l,variables:{file:new File([e],`audio.mp3`,{type:`audio/mpeg`})},context:{hasUpload:!0}})).then(e=>c(a(e.data?.transcribe||`[]`,[]))).catch(e=>(d.error(f(`Error transcribing file`),e),c())):(d.denied(),Promise.resolve(c()))}function p(n,i,a=null,s=null){let c=r(),l=e(),{$gettext:d}=o;return c.can(`text:translate`)?(Array.isArray(n)||(n=[n].filter(e=>!!e)),n.length?i?t.mutate({mutation:u,variables:{texts:n,to:i.toUpperCase(),from:a?.toUpperCase(),context:s}}).then(e=>e.data?.translate||[]).catch(e=>{l.error(d(`Error translating texts`),e)}):Promise.reject(Error(`Target language is required`)):Promise.resolve([])):l.denied()}function m(n,i=[],a=[]){let s=r(),c=e(),{$gettext:l}=o;return s.can(`text:write`)?(n=String(n).trim(),n?(Array.isArray(i)||(i=[i]),i.push(`Only return the requested data without any additional information`),t.mutate({mutation:d,variables:{prompt:n,context:i.filter(e=>!!e).join(`
`),files:a.filter(e=>!!e)}}).then(e=>e.data?.write?.replace(/^"(.*)"$/,`$1`)||``).catch(e=>{c.error(l(`Error generating text`),e)})):(c.add(l(`Prompt is required for generating text`),`error`),Promise.resolve(``))):c.denied()}export{f as transcribe,p as translate,m as write};