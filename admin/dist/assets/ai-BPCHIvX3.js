import{_ as e,f as t,x as n}from"./stores-Bm6PX1aQ.js";import{s as r}from"./graphql-BRoDkLYC.js";import{r as i}from"./json-Dnt6XAOd.js";import{t as a}from"./i18n-CjIdYn2x.js";import{c as o}from"./index-CfOwHhUR.js";import{toMp3 as s,transcription as c}from"./audio-CMbM47Vs.js";var l=r`
  mutation ($file: Upload!) {
    transcribe(file: $file)
  }
`,u=r`
  mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
    translate(texts: $texts, to: $to, from: $from, context: $context)
  }
`,d=r`
  mutation ($prompt: String!, $context: String, $files: [String!]) {
    write(prompt: $prompt, context: $context, files: $files)
  }
`;function f(r){let u=e(),d=t(),{$gettext:f}=a;return u.can(`audio:transcribe`)?s(o(r,!0)).then(e=>n.mutate({mutation:l,variables:{file:new File([e],`audio.mp3`,{type:`audio/mpeg`})},context:{hasUpload:!0}})).then(e=>c(i(e.data?.transcribe||`[]`,[]))).catch(e=>(d.error(f(`Error transcribing file`),e),c())):(d.denied(),Promise.resolve(c()))}function p(r,i,o=null,s=null){let c=e(),l=t(),{$gettext:d}=a;return c.can(`text:translate`)?(Array.isArray(r)||(r=[r].filter(e=>!!e)),r.length?i?n.mutate({mutation:u,variables:{texts:r,to:i.toUpperCase(),from:o?.toUpperCase(),context:s}}).then(e=>e.data?.translate||[]).catch(e=>{l.error(d(`Error translating texts`),e)}):Promise.reject(Error(`Target language is required`)):Promise.resolve([])):Promise.resolve(l.denied())}function m(r,i=[],o=[]){let s=e(),c=t(),{$gettext:l}=a;return s.can(`text:write`)?(r=String(r).trim(),r?(Array.isArray(i)||(i=[i]),i.push(`Only return the requested data without any additional information`),n.mutate({mutation:d,variables:{prompt:r,context:i.filter(e=>!!e).join(`
`),files:o.filter(e=>!!e)}}).then(e=>e.data?.write?.replace(/^"(.*)"$/,`$1`)||``).catch(e=>{c.error(l(`Error generating text`),e)})):(c.add(l(`Prompt is required for generating text`),`error`),Promise.resolve(``))):Promise.resolve(c.denied())}export{f as transcribe,p as translate,m as write};